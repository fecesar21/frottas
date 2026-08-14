# Reset de senha solicitado pelo operador

**Data:** 2026-08-13
**Status:** Aprovado

## Contexto

Hoje não existe nenhum mecanismo de auto-atendimento para operadores que esqueceram a senha. O único caminho é um admin/gestor alterar manualmente via `PATCH /api/usuarios/{id}` (campo `senha`). Isso já cobre o caso de operadores sem e-mail cadastrado, mas força uma dependência humana para o caso comum.

Fatos relevantes do sistema atual:
- Login (`AuthController::login`) autentica por **CPF** + senha (não por `usuario`/e-mail, ao contrário do que o CLAUDE.md descreve — CLAUDE.md desatualizado nesse ponto, fora de escopo corrigir aqui).
- `Usuario.email` é `nullable` e `unique` — nem todo operador tem e-mail cadastrado.
- Senha é armazenada em `senha_hash`, validada no cadastro/edição com regra `min:6` + `regex:/^[0-9]+$/` (somente dígitos).
- `MAIL_MAILER=log` em dev/staging atual — e-mails de reset caem no log, não são enviados de fato. Produção precisará de um mailer real configurado (fora de escopo desta spec).
- Existe uma tabela `password_reset_tokens` (`email` PK, `token`, `created_at`) criada pelo scaffold padrão do Laravel, nunca usada (o guard de auth é `Usuario`, não o `User` padrão). Será reaproveitada.
- Não há frontend web no projeto (API pura). O link de reset aponta para uma URL configurável via env (`APP_FRONTEND_URL`), a ser consumida por um cliente futuro (app/mobile/SPA).

## Decisão de arquitetura

Endpoints próprios, sem usar o `Password` broker nativo do Laravel (que exigiria configurar um segundo guard/provider em `config/auth.php` para o model `Usuario`). Mantém consistência com o restante do `AuthController`, que já implementa login/logout de forma direta sem abstrações de guard extras.

## Endpoints

### `POST /api/auth/esqueci-senha`

- **Middleware:** público, `throttle:esqueci-senha` (novo limiter dedicado, análogo a `throttle:login`).
- **Request:** `{ "email": "string" }` — obrigatório, formato de e-mail.
- **Comportamento:**
  1. Busca `Usuario` ativo (`ativo = true`) com esse `email`.
  2. Se encontrado: gera token aleatório de 64 caracteres (`Str::random(64)`), grava na tabela `password_reset_tokens` como `['email' => ..., 'token' => Hash::make($token), 'created_at' => now()]` (upsert — sobrescreve token anterior do mesmo e-mail).
  3. Envia uma `Notification` (`ViaMail`) com link `{APP_FRONTEND_URL}/redefinir-senha?token={token}&email={email}` (token em texto plano só no e-mail; no banco fica hasheado, mesmo padrão do Laravel nativo).
  4. Se não encontrado, ou usuário inativo, ou sem e-mail cadastrado: não faz nada.
  5. **Resposta é sempre a mesma** (200, mensagem genérica), independente do e-mail existir ou não — evita enumeração de contas.
- **Resposta:** `{ "message": "Se o e-mail informado estiver cadastrado, você receberá instruções para redefinir sua senha." }`

### `POST /api/auth/redefinir-senha`

- **Middleware:** público, `throttle:esqueci-senha` (mesmo limiter).
- **Request:** `{ "email": "string", "token": "string", "senha": "string" }`.
  - `senha`: mesma regra de validação usada em `UpdateUsuarioRequest` (`required`, `min:6`, `regex:/^[0-9]+$/`).
- **Comportamento:**
  1. Busca registro em `password_reset_tokens` pelo `email`.
  2. Se não existe, ou `created_at` mais antigo que 60 minutos, ou `Hash::check($token, $registro->token)` falha: retorna 422 com erro genérico (`"Token inválido ou expirado"`).
  3. Busca `Usuario` ativo pelo e-mail (defesa extra — o usuário pode ter sido desativado entre a solicitação e o reset).
  4. Atualiza `senha_hash` com o novo hash.
  5. Revoga todos os tokens Sanctum do usuário (`$usuario->tokens()->delete()`) — força novo login em todos os dispositivos.
  6. Apaga o registro de `password_reset_tokens` (uso único).
- **Resposta:** `{ "message": "Senha redefinida com sucesso." }`

## Rate limiting

Novo limiter `esqueci-senha` em `RouteServiceProvider` (ou onde os demais `throttle:login`/`throttle:login-ad` estão definidos), ex.: 5 tentativas por 15 minutos por IP + e-mail combinados — mesmo padrão dos limiters de login existentes.

## Notification / e-mail

Nova classe `App\Notifications\RedefinicaoSenhaNotification` (implementa `ShouldQueue` para não bloquear a resposta HTTP — a fila já roda via Supervisor em produção). Corpo simples: saudação, link, aviso de expiração em 60 minutos, aviso "se você não solicitou, ignore este e-mail".

## Configuração

Nova variável de ambiente `APP_FRONTEND_URL` (`.env` / `.env.example`), com fallback sensato para ambiente de dev (ex.: `http://localhost:5173`).

## Testes (TDD)

- `esqueci-senha` com e-mail existente e ativo → 200, token gravado no banco, notificação enfileirada (`Notification::fake()`).
- `esqueci-senha` com e-mail inexistente → 200, mesma mensagem, nada gravado, nenhuma notificação.
- `esqueci-senha` com e-mail de usuário inativo → 200, mesma mensagem, nada gravado.
- `esqueci-senha` sem e-mail no payload → 422 (validação).
- `redefinir-senha` com token válido e senha válida → 200, `senha_hash` atualizado, consegue logar com a nova senha, tokens Sanctum antigos revogados, registro do token apagado.
- `redefinir-senha` com token errado → 422.
- `redefinir-senha` com token expirado (`created_at` > 60min) → 422.
- `redefinir-senha` com senha em formato inválido (não numérica / curta) → 422.
- `redefinir-senha` reaproveitando o mesmo token duas vezes → segunda tentativa falha (422, já apagado).
- Rate limiting: excesso de tentativas em `esqueci-senha` → 429.

## Fora de escopo

- Configurar um mailer real de produção (mantém `MAIL_MAILER=log` como está; documentar necessidade de configurar antes de ir para produção).
- Corrigir a divergência entre CLAUDE.md (menciona login por `usuario`/e-mail) e o comportamento real (login por CPF).
- Construir a tela de frontend que consome `APP_FRONTEND_URL` — fora do escopo deste backend API-only.
- Endpoint administrativo de reset — já existe (`PATCH /api/usuarios/{id}`).
