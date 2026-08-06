# Onboarding de uma nova unidade ao login via LDAP/AD

Este runbook documenta o processo para habilitar o login via Active Directory (login de "solicitante") para uma nova unidade, com base no processo real feito para a UPA Anatólio Dias Carneiro.

## Contexto importante

**Cada unidade tem seu próprio Active Directory / Domain Controller.** O sistema hoje suporta apenas **uma conexão LDAP por vez** (`config('ldap.connections.default')`), então cada nova unidade exige repetir este processo com os dados daquele AD específico. Se duas unidades precisarem de login simultâneo, será necessário estender `config/ldap.php` para múltiplas conexões nomeadas (não implementado ainda — avaliar quando a segunda unidade for integrada).

## Pré-requisitos (levantar com o TI da unidade)

1. **Endereço do Domain Controller** (IP ou hostname) e se LDAPS (porta 636) está habilitado.
2. **Base DN** da OU onde ficam os usuários comuns (funcionários) — não a OU de contas de serviço/admin.
3. **Conta de serviço somente leitura** no AD (usuário + senha) para o sistema consultar usuários. Não precisa de privilégio de escrita.
4. **Qual atributo do AD identifica a unidade física** do funcionário. **Não assuma** que `department` ou `company` funcionam — confirme com uma busca real (ver Passo 3). Nesta empresa, `department` guarda o **setor** (Enfermagem, Médicos...), não a unidade; `company` acabou sendo útil porque, *nesse AD específico*, ele é constante e corresponde à unidade em questão (ver Passo 4).

## Passo 1 — Configurar `.env` de produção

No servidor (`ssh fcesarc@192.168.1.6`, diretório `/var/www/frottas`), adicionar/editar no `.env`:

```
LDAP_HOST=<ip-ou-host-do-dc>
LDAP_BASE_DN="<base dn completo, com aspas se tiver acentos/espaços>"
LDAP_USERNAME=<usuario>@<dominio>
LDAP_PASSWORD="<senha>"
LDAP_PORT=636
LDAP_USE_SSL=true
LDAP_UNIDADE_ATTRIBUTE=<atributo a confirmar no Passo 3>
```

Depois: `php artisan config:clear && php artisan config:cache`.

**Atenção:** se já existir uma unidade configurada (LDAP de outra unidade), este processo **sobrescreve** a configuração atual — o sistema só suporta uma conexão até `config/ldap.php` ser estendido para múltiplas.

## Passo 2 — Testar conectividade bruta (antes de confiar no LdapRecord)

Testar em duas camadas, na ordem, direto no servidor via SSH:

**a) TCP básico:**
```bash
nc -zv -w5 <dc-ip> 636
```

**b) Bind real via `ldap_bind` do PHP, sem passar pelo Laravel** (evita logging e cache de config, dá o erro real na hora):
```bash
php -r '
ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
$conn = ldap_connect("ldaps://<dc-ip>", 636);
ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 8);
$bind = @ldap_bind($conn, "<usuario>@<dominio>", "<senha>");
echo $bind ? "BIND OK" . PHP_EOL : "BIND FALHOU: " . ldap_error($conn) . PHP_EOL;
'
```

**Se falhar com "Can't contact LDAP server":** quase sempre é TLS — ou LDAPS não está habilitado no DC (pedir ao TI para habilitar/instalar certificado), ou o certificado é de uma CA interna não reconhecida pelo sistema. Neste segundo caso, `config/ldap.php` já vem preparado com:

```php
'options' => [
    LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
],
```

que desabilita a validação da cadeia do certificado (a conexão continua criptografada via LDAPS — só não valida a autoridade emissora). Isso já é a configuração padrão do projeto; normalmente não precisa mexer nesse arquivo para uma nova unidade, só se o comportamento for diferente.

## Passo 3 — Descobrir o atributo correto de unidade

Com o bind funcionando, listar uma amostra de usuários reais e inspecionar os atributos candidatos (`department`, `company`, e outros se necessário — `physicalDeliveryOfficeName`, `l` (city), etc.):

```bash
php -r '
ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
$conn = ldap_connect("ldaps://<dc-ip>", 636);
ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_bind($conn, "<usuario>@<dominio>", "<senha>");
$r = ldap_search($conn, "<base-dn>", "(objectClass=user)", ["samaccountname","company","department"], 0, 15);
$e = ldap_get_entries($conn, $r);
for ($i=0; $i<min(15,$e["count"]); $i++) {
  echo "- sam=".($e[$i]["samaccountname"][0] ?? "?")." company=".($e[$i]["company"][0] ?? "(vazio)")." dept=".($e[$i]["department"][0] ?? "(vazio)").PHP_EOL;
}
'
```

Analisar: qual atributo tem um valor que **identifica a unidade física** de forma útil?
- Se **um único valor constante** aparece para todos (como aconteceu com `company` = "UPA" nesta unidade porque o AD inteiro é dela) → ótimo, um único mapeamento resolve tudo (Passo 4).
- Se o valor **varia por funcionário mas representa unidade** (não setor) → normal, cada valor distinto vira uma linha de mapeamento.
- Se nenhum atributo padrão serve, verificar a estrutura de OUs (`ldapsearch`/`ldap_search` por `objectClass=organizationalUnit`) — pode ser necessário usar o componente do DN do usuário como identificador (não implementado no código hoje; avaliar mudança se for o caso).

## Passo 4 — Cadastrar o(s) mapeamento(s) de unidade

Via tinker no servidor:

```bash
php artisan tinker --execute='
$unidade = App\Models\Unidade::where("nome", "<nome exato da Unidade cadastrada>")->firstOrFail();
App\Models\UnidadeAdMapeamento::updateOrCreate(
    ["valor_ad" => "<valor encontrado no Passo 3>"],
    ["unidade_id" => $unidade->id]
);
'
```

Repetir uma vez por valor distinto do atributo, se a unidade tiver múltiplos valores possíveis (ex.: vários hospitais/setores mapeando cada um para sua `Unidade` correspondente).

## Passo 5 — Testar

**Nunca** simular login com a senha de um funcionário real digitada por você — peça para a própria pessoa testar pela tela de login do app de solicitação. Antes disso, é possível validar sem senha real:

- Confirmar que a busca por `sAMAccountName` de um funcionário conhecido retorna resultado (Passo 3 já cobre isso).
- Testar uma senha propositalmente errada contra `/api/auth/login-ad` — deve retornar `401` com mensagem genérica (confirma que a stack toda está de pé, sem 503 de conectividade).

```bash
curl -s -H 'Host: 192.168.1.6' -X POST http://localhost/api/auth/login-ad \
  -H 'Content-Type: application/json' \
  -d '{"usuario":"<sam de um funcionario real>","senha":"senha-propositalmente-errada"}'
```

Depois, pedir a um funcionário real (idealmente alguém que **ainda não tenha** conta manual no sistema, para testar o caminho de auto-provisionamento do zero) para logar pela tela.

## Armadilhas conhecidas

- **Log do Laravel com permissão só de `www-data`:** se for depurar via `php artisan tinker` como usuário `fcesarc` (SSH) num dia em que o arquivo de log de hoje já foi criado por uma requisição real (dono `www-data`, sem escrita de grupo), comandos que geram log falham com "Permission denied" ao tentar logar — mesmo que a operação LDAP em si tenha funcionado. Contornar testando via `curl` contra o endpoint real (roda como `www-data`, que é dono do arquivo) em vez de `tinker` direto.
- **E-mail duplicado:** se o e-mail retornado pelo AD já pertence a uma conta cadastrada manualmente (ex.: um admin/gestor que também está no AD), o sistema **vincula a conta existente** ao invés de criar uma nova — sem alterar o perfil dela. Isso já está implementado; não é necessário fazer nada manualmente para esse caso, mas é bom saber que pode acontecer com frequência se muitos admins/gestores também tiverem conta no AD.
- **`opcache.validate_timestamps` está ligado** em produção, então um `git pull` de mudanças de código PHP já é pego automaticamente pelo PHP-FPM na próxima requisição — não é necessário reiniciar o serviço. Ainda assim, sempre rodar `php artisan optimize:clear` após qualquer deploy manual fora do `scripts/deploy.sh`.
