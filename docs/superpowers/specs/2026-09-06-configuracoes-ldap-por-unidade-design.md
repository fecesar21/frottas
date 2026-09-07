# Configurações Admin: LDAP por Unidade

## Contexto

Cada unidade (hospital/filial) atendida pelo sistema possui seu próprio Active
Directory / Domain Controller, com credenciais, host e atributos distintos.
Hoje o login via AD (`POST /api/auth/login-ad`, ver
[2026-08-05-login-ldap-solicitante-design.md](2026-08-05-login-ldap-solicitante-design.md))
suporta **uma única conexão LDAP global**, fixada em `config/ldap.php` a
partir de variáveis de ambiente (`LDAP_HOST`, `LDAP_USERNAME`, etc.). Ativar
uma nova unidade hoje exige sobrescrever o `.env` de produção e reimplantar —
processo manual documentado em
`docs/runbooks/onboarding-unidade-ldap.md` — o que impede duas unidades
autenticarem simultaneamente e não dá a um Admin nenhuma forma de gerenciar
isso pela aplicação.

A tabela `unidade_ad_mapeamentos` já existe para resolver **a unidade** de um
usuário a partir de um atributo do AD após o bind, mas pressupõe que o bind já
aconteceu contra a única conexão configurada — não resolve **contra qual AD**
tentar autenticar quando existem múltiplos.

## Escopo

- Nova área **Configurações** no menu Admin (hub genérico, pronta para
  crescer; hoje contém apenas a seção LDAP).
- CRUD (via UI) de configuração LDAP por Unidade: host, porta, base DN,
  credenciais da service account, SSL/StartTLS, atributo de unidade no AD,
  valor(es) desse atributo que identificam a unidade, ativo/inativo.
- Botão "Testar Conexão" que faz um bind real com a service account antes de
  salvar.
- `AuthController@loginAd` passa a tentar, em sequência, cada configuração de
  unidade ativa, até um bind de usuário funcionar (usuário não escolhe
  unidade no login — decisão já validada com o usuário).
- Migração dos dados hoje em `.env` (produção) e em `unidade_ad_mapeamentos`
  para a nova tabela, e remoção do fluxo antigo de conexão única.

Fora de escopo: outras seções do hub de Configurações além de LDAP; alterar o
fluxo de login local (CPF/senha) de admin/gestor/operador; SSO/Kerberos.

## Backend

### Migration — nova tabela `unidade_ldap_configuracoes`

```php
Schema::create('unidade_ldap_configuracoes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('unidade_id')->unique()->constrained('unidades')->cascadeOnDelete();
    $table->string('host');
    $table->unsignedInteger('port')->default(636);
    $table->string('base_dn');
    $table->string('username');       // service account (somente leitura)
    $table->text('password');         // criptografado via cast Eloquent
    $table->boolean('use_ssl')->default(true);
    $table->boolean('use_starttls')->default(false);
    $table->string('unidade_attribute')->default('department');
    $table->json('valores_ad');       // valores do atributo que identificam esta unidade, ex: ["HOSP-CENTRO"]
    $table->boolean('ativo')->default(true);
    $table->timestamps();
});
```

- `unidade_id` é `unique` — uma configuração LDAP por unidade (1:1). Simples
  e suficiente: nenhuma unidade real precisa de dois ADs distintos hoje.
- `valores_ad` substitui `unidade_ad_mapeamentos`: ao invés de uma tabela
  separada mapeando `valor_ad → unidade_id`, cada configuração já carrega os
  valores que a identificam (um AD pode ter mais de um valor de
  `department`/`company` correspondendo à mesma unidade física — caso comum
  descrito no runbook).
- Migration de dados (`up()` da própria migration ou uma migration seguinte):
  para cada linha de `unidade_ad_mapeamentos`, agrupar por `unidade_id` e
  criar/atualizar uma `unidade_ldap_configuracoes` com `valores_ad` = array
  dos `valor_ad` associados; preencher `host`/`username`/`password`/etc. a
  partir dos valores atuais de `config('ldap.connections.default')` (lidos
  via `env()` diretamente na migration, já que é uma cópia pontual). Depois
  dessa migração, `unidade_ad_mapeamentos` e `config/ldap.php` deixam de ser
  referenciados pelo código novo — a tabela antiga é removida numa migration
  posterior (mantida por um ciclo de deploy para permitir rollback simples).

### Model `UnidadeLdapConfiguracao`

```php
class UnidadeLdapConfiguracao extends Model
{
    use HasUuids;

    protected $table = 'unidade_ldap_configuracoes';

    protected $fillable = [
        'unidade_id', 'host', 'port', 'base_dn', 'username', 'password',
        'use_ssl', 'use_starttls', 'unidade_attribute', 'valores_ad', 'ativo',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'valores_ad' => 'array',
        'use_ssl' => 'boolean',
        'use_starttls' => 'boolean',
        'ativo' => 'boolean',
    ];

    protected $hidden = ['password'];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function paraConexaoLdap(): array
    {
        return [
            'hosts' => [$this->host],
            'port' => $this->port,
            'base_dn' => $this->base_dn,
            'username' => $this->username,
            'password' => $this->password,
            'use_ssl' => $this->use_ssl,
            'use_tls' => $this->use_starttls,
            'timeout' => 5,
            'options' => [
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
            ],
        ];
    }
}
```

`$hidden = ['password']` garante que o `GET` da API nunca retorne a senha,
mesmo criptografada — o frontend mostra apenas "Senha configurada" com opção
de substituir (campo vazio no `PUT` = mantém a senha atual).

### `AuthController@loginAd` — reescrito para múltiplas unidades

Fluxo novo:

1. Buscar todas as `UnidadeLdapConfiguracao::where('ativo', true)->get()`.
2. Para cada uma, registrar dinamicamente uma conexão LdapRecord nomeada
   (`Container::getInstance()->addConnection(new Connection($config->paraConexaoLdap()), $nomeUnico)`)
   e tentar localizar + autenticar o usuário nessa conexão específica
   (`LdapUser::on($nomeUnico)->findBy('samaccountname', ...)`, depois bind com
   o DN encontrado).
3. Qualquer exceção de conectividade (`LdapRecordException`) numa unidade é
   logada (`Log::warning`, incluindo `unidade_id`) e a próxima é tentada — não
   aborta o fluxo. Erro genérico 503 só é retornado se **todas** as unidades
   ativas falharem por conectividade (nenhuma respondeu).
4. No primeiro bind bem-sucedido: resolve `unidade_id` a partir da própria
   `UnidadeLdapConfiguracao` que autenticou (não precisa mais reconsultar
   `valores_ad` via atributo — já se sabe qual unidade autenticou o bind;
   ainda assim, se quisermos manter granularidade por setor dentro da mesma
   unidade via atributo, isso é resolvido no passo seguinte, opcional/mantido
   como está hoje: cruzar `unidade_attribute`/`valores_ad` se a config tiver
   múltiplos valores mapeando sub-setores para a mesma unidade — na prática
   `unidade_id` já é 1:1 com a config que autenticou, então o cruzamento vira
   apenas uma validação, não uma segunda fonte de verdade).
5. Se nenhuma unidade autenticar: `401` genérico (mesma mensagem atual,
   preserva a não-distinção entre "usuário não existe" e "senha errada").
6. Resto do fluxo (auto-provisionamento, vínculo por e-mail, sync de
   campos, criação de token) **inalterado** em relação à implementação atual.

Extraio a lógica de resolução ("dado usuário+senha, encontrar em qual
unidade autentica") para um método privado `resolverLoginLdap(string $usuario, string $senha): ?array` que retorna `['ldapUser' => ..., 'unidadeConfig' => ...]` ou `null`, mantendo o método público enxuto.

### Endpoint de teste de conexão

`POST /api/unidades/{unidade}/ldap-config/testar` (middleware `admin`)

Recebe os mesmos campos do formulário (não precisa estar salvo ainda —
permite testar antes de persistir). Monta uma conexão LdapRecord avulsa e
tenta:
1. Bind da service account (`username`/`password`) — se falhar, erro
   "Falha ao autenticar a conta de serviço: <mensagem>".
2. Uma busca simples (`base_dn`, filtro `(objectClass=user)`, limit 1) — se
   zero resultados, aviso (não erro fatal) "Conexão OK, mas nenhum usuário
   encontrado no Base DN informado — confira o Base DN".

Retorna `{ sucesso: bool, mensagem: string }`. Nunca persiste nada.

### CRUD de configuração (admin-only)

```
GET  /api/unidades/{unidade}/ldap-config       -> config atual (sem password) ou 404 se não configurada
PUT  /api/unidades/{unidade}/ldap-config       -> cria ou atualiza (upsert); password opcional no update (vazio = mantém)
DELETE /api/unidades/{unidade}/ldap-config     -> remove a configuração (unidade passa a não participar do login AD)
```

`UnidadeLdapConfigController` novo, dentro do grupo de rotas já protegido por
`middleware('admin')` (mesmo grupo de `usuarios`).

`UpsertUnidadeLdapConfigRequest`: valida `host` (required|string), `port`
(required|integer|1-65535), `base_dn` (required|string), `username`
(required|string), `password` (nullable|string — nullable só no update),
`use_ssl`/`use_starttls` (boolean), `unidade_attribute` (required|string),
`valores_ad` (required|array|min:1, cada item string não vazia), `ativo`
(boolean).

## Frontend

### Menu e rotas

- `Sidebar.jsx`: novo item no bloco ADMIN — `{ to: '/configuracoes', label: 'Configurações', icon: Settings }`.
- `FleetApp.jsx`: novas rotas dentro de `AdminRoute`:
  ```jsx
  <Route path="/configuracoes" element={<AdminRoute><ConfiguracoesHub /></AdminRoute>} />
  <Route path="/configuracoes/ldap" element={<AdminRoute><ConfiguracoesLdap /></AdminRoute>} />
  ```
- `ConfiguracoesHub.jsx`: página simples com cards de navegação por seção
  (hoje só um card "LDAP por Unidade" → `/configuracoes/ldap`). Estrutura
  igual à listagem de outras páginas admin (reaproveita `Card.jsx`).

### `ConfiguracoesLdap.jsx`

- Lista todas as `Unidade` (reaproveita `unidadesApi.listar()`), cada linha
  mostrando: nome da unidade, status da config (Configurada / Não
  configurada / Inativa), botão "Configurar" / "Editar".
- Ao clicar, abre `Modal` (reaproveita `components/ui/Modal.jsx`) com
  `UnidadeLdapConfigForm`: campos host, porta, base DN, usuário, senha
  (placeholder "•••••• (deixe em branco para manter)" quando já existe
  config), SSL/StartTLS (checkboxes), atributo de unidade, valores AD (input
  de tags/lista separada por vírgula), ativo.
- Botão **"Testar Conexão"** dentro do form, chama o endpoint de teste com os
  valores atuais do formulário (mesmo sem salvar) e mostra `Alert`
  success/error com a mensagem retornada.
- Botão "Salvar" faz `PUT`, invalida query `['unidade-ldap-config', unidadeId]`.

### API client

Novo `resources/js/api/unidadeLdapConfig.js`:
```js
export const buscar = (unidadeId) => api.get(`/unidades/${unidadeId}/ldap-config`)
export const salvar = (unidadeId, data) => api.put(`/unidades/${unidadeId}/ldap-config`, data)
export const remover = (unidadeId) => api.delete(`/unidades/${unidadeId}/ldap-config`)
export const testar = (unidadeId, data) => api.post(`/unidades/${unidadeId}/ldap-config/testar`, data)
```

## Testes

- `UnidadeLdapConfigApiTest`: CRUD completo, autorização (não-admin recebe
  403), password nunca aparece no `GET`, update com password vazio mantém a
  anterior, validação de `valores_ad` vazio.
- `TestarConexaoLdapTest`: mock de bind (`DirectoryEmulator`) sucesso e
  falha, sem persistir nada.
- `LoginAdTest` (reescrito): cenários adaptados para múltiplas
  `UnidadeLdapConfiguracao` — bind falha na config da unidade A e sucede na
  B; nenhuma unidade autentica → 401; unidade com config inativa é ignorada
  mesmo com credenciais válidas nela; demais cenários (auto-provisionamento,
  vínculo por e-mail, usuário inativo, GUID ausente) mantidos, adaptados
  para a nova origem da config.
- Migration de dados: teste que roda a migration sobre um cenário com
  `unidade_ad_mapeamentos` populado e confere que a `unidade_ldap_configuracoes`
  resultante agrupa corretamente os `valores_ad`.

## Rollout

1. Deploy da migration (cria tabela nova + migra dados de `.env` atual e de
   `unidade_ad_mapeamentos` para a unidade já configurada em produção hoje).
2. Deploy do código (`AuthController`, novas rotas, frontend).
3. Validar em produção: login AD da unidade já configurada continua
   funcionando (sem digitar nada na UI — dados já migrados automaticamente).
4. Onboarding de novas unidades passa a ser feito pela tela de
   Configurações, não mais editando `.env` — `docs/runbooks/onboarding-unidade-ldap.md`
   é atualizado para refletir o novo processo (Passo 1 e 4 do runbook atual
   deixam de existir; Passos 2 e 3 — descobrir host/base DN/atributo com o
   TI da unidade — continuam necessários antes de preencher a tela).
