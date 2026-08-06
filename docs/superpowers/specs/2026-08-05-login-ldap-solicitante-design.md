# Login via LDAP/Active Directory para solicitantes de transporte

## Contexto

Hoje o cadastro de "solicitante" de transporte não existe como perfil no sistema (`usuarios.perfil` só tem `admin`, `gestor`, `operador`), e o app de solicitação (`resources/solicitacao-js`) usa o mesmo login CPF+senha local do restante do sistema. Como a base de solicitantes passará a ser toda a empresa (1000+ pessoas), cadastro manual não é viável. O objetivo é autenticar solicitantes contra o Active Directory corporativo usando o usuário e a senha de rede (Windows) que já possuem, sem exigir cadastro prévio.

## Escopo

- Novo perfil `solicitante` em `usuarios.perfil`.
- Novo endpoint de login `POST /api/auth/login-ad`, exclusivo para o app de solicitação, autenticando via bind LDAP direto contra o AD.
- Auto-provisionamento do `Usuario` local no primeiro login bem-sucedido, correlacionado pelo `objectGUID` do AD (imutável).
- Resolução de `unidade_id` a partir de um atributo do AD (`department`/`company`), via tabela de mapeamento administrável (`unidade_ad_mapeamentos`).
- Ajuste do frontend `solicitacao-js` (tela `Login.jsx` + `AuthContext`) para usar o novo endpoint.

Fora de escopo: login de `admin`/`gestor`/`operador` (continuam CPF+senha local, sem mudanças); tela de administração de mapeamentos AD→Unidade (pode ser CRUD simples via Tinker/seeder no primeiro momento, tela de UI fica para uma iteração futura se necessário); SSO/Kerberos/NTLM transparente (login continua exigindo digitação de usuário/senha, não é autenticação integrada do navegador).

## Backend

### Pacote

`directorytree/ldaprecord-laravel` — biblioteca LDAP mais madura para Laravel, suporta bind direto de usuário e LDAPS nativamente.

### Configuração (`.env` / `config/ldap.php`)

```
LDAP_HOST=
LDAP_BASE_DN=
LDAP_USERNAME=          # service account somente-leitura, usada só para localizar o DN do usuário
LDAP_PASSWORD=
LDAP_PORT=636
LDAP_USE_SSL=true
LDAP_UNIDADE_ATTRIBUTE=department   # atributo AD usado para resolver a unidade
```

A service account tem permissão apenas de leitura no AD — nunca é usada para validar a senha do solicitante (isso é feito via bind direto, ver abaixo).

### Migrations

1. Ajuste do enum de perfil:
```php
// altera usuarios.perfil para incluir 'solicitante'
$table->enum('perfil', ['admin', 'gestor', 'operador', 'solicitante'])->default('operador')->change();
```
(Requer `doctrine/dbal` para `->change()` em SQLite; instalar como dependência se ainda não presente — confirmar na fase de implementação.)

2. Novos campos em `usuarios`:
```php
$table->string('ldap_guid')->nullable()->unique()->after('cpf');
$table->timestamp('ldap_sync_at')->nullable();
```
`cpf` e `senha_hash` passam a aceitar `null` (solicitantes não têm senha local nem CPF obrigatoriamente cadastrado).

3. Nova tabela `unidade_ad_mapeamentos`:
```php
Schema::create('unidade_ad_mapeamentos', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('valor_ad')->unique();   // valor bruto do atributo AD, ex: "HOSP-CENTRO"
    $table->foreignUuid('unidade_id')->constrained('unidades');
    $table->timestamps();
});
```

### Model `Usuario`

- Adicionar `ldap_guid`, `ldap_sync_at` ao `$fillable`.
- Nenhuma mudança em `getAuthPassword()` — solicitantes não autenticam via Sanctum password check, apenas recebem token após validação LDAP bem-sucedida (mesmo fluxo de criação de token que os demais perfis).

### `AuthController@loginAd` (novo método)

```php
public function loginAd(LoginAdRequest $r)
{
    $input = $r->validated(); // ['usuario' => sAMAccountName, 'senha' => string]

    $ldapUser = \LdapRecord\Models\ActiveDirectory\User::findBy('samaccountname', $input['usuario']);

    if (!$ldapUser || !$ldapUser->getConnection()->auth()->attempt($ldapUser->getDn(), $input['senha'])) {
        return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
    }

    $guid = $ldapUser->getConvertedGuid();
    $unidadeAtributo = $ldapUser->getFirstAttribute(config('ldap.unidade_attribute'));

    $usuario = Usuario::firstOrNew(['ldap_guid' => $guid]);
    $usuario->fill([
        'nome'      => $ldapUser->getFirstAttribute('displayname'),
        'email'     => $ldapUser->getFirstAttribute('mail'),
        'perfil'    => 'solicitante',
        'ativo'     => true,
        'unidade_id' => optional(
            UnidadeAdMapeamento::where('valor_ad', $unidadeAtributo)->first()
        )->unidade_id,
        'ldap_sync_at' => now(),
    ]);
    $usuario->save();

    $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

    return response()->json([
        'token' => $token,
        'user'  => $this->buildUserPayload($usuario),
    ]);
}
```

Pontos importantes:
- O bind de validação de senha é feito com o **DN do próprio usuário** encontrado (`$ldapUser->getDn()`), nunca com a service account — é assim que se valida senha de AD via LDAP (o protocolo não permite ler/comparar hash).
- A cada login, os campos `nome`/`email`/`unidade_id` são ressincronizados (cobre transferência de setor, mudança de nome, etc).
- Se `valor_ad` não tiver mapeamento cadastrado, `unidade_id` fica `null` — o solicitante consegue logar, mas o frontend deve bloquear a criação de solicitação com uma mensagem orientando a contatar o administrador.

### `LoginAdRequest`

Validação simples: `usuario` e `senha` obrigatórios, strings. Sem regra de formato (sAMAccountName não segue padrão de CPF).

### Rotas (`routes/api.php`)

```php
Route::post('/auth/login-ad', [AuthController::class, 'loginAd'])
    ->middleware('throttle:5,1');
```

### Tratamento de erros

| Cenário | Resposta |
|---|---|
| Usuário não existe no AD, ou senha incorreta | `401` — `"Usuário ou senha inválidos"` (mensagem genérica, não revela qual dos dois falhou) |
| DC inacessível / timeout de conexão | `503` — `"Serviço de autenticação indisponível, tente novamente"` (log detalhado do erro LDAP via `Log::error`, sem detalhe na resposta) |
| Login OK, mas sem mapeamento de unidade | `200` normal; `user.unidade_id = null`. Frontend do app de solicitação trata esse estado. |
| Rate limit excedido | `429` padrão do Laravel (`throttle:5,1`) |

Não há throttle adicional além do padrão Laravel — a política de bloqueio de conta por tentativas erradas já é responsabilidade do AD.

## Frontend (`resources/solicitacao-js`)

### `Login.jsx`

- Placeholder do campo "Usuário" passa a indicar "usuário de rede" (ex.: `placeholder="usuário de rede (ex: jsilva)"`).
- Nenhuma outra mudança estrutural — o formulário já captura `{ usuario, senha }`.

### `AuthContext`

- Função `login()` passa a chamar `POST /api/auth/login-ad` em vez de `/api/auth/login`.

### Tratamento do estado "sem unidade"

- Nas telas de criação de solicitação, se `user.unidade_id === null`, exibir aviso bloqueando o envio: "Sua unidade ainda não foi configurada. Contate o administrador do sistema."

## Testes

- Feature test para `loginAd`: mockar o LdapRecord (usar `LdapRecord\Laravel\Testing` fake/`DirectoryFake`) cobrindo: credencial válida com usuário novo (cria `Usuario`), credencial válida com usuário existente (atualiza campos), credencial inválida (401), unidade sem mapeamento (`unidade_id` null).
- Teste de que login `admin`/`gestor`/`operador` via `/api/auth/login` continua inalterado.
