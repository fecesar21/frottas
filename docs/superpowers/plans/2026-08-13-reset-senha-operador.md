# Reset de Senha do Operador — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que o operador redefina a própria senha via e-mail, sem depender de um admin, mantendo o fallback administrativo existente para quem não tem e-mail cadastrado.

**Architecture:** Dois novos endpoints públicos no `AuthController` (`esqueciSenha` / `redefinirSenha`), reaproveitando a tabela `password_reset_tokens` já existente no schema (criada pelo scaffold padrão do Laravel, nunca usada). Nenhum broker nativo do Laravel — lógica direta, no mesmo estilo do restante do `AuthController`. Uma `Notification` nova (`RedefinicaoSenhaNotification`, `ShouldQueue`) envia o e-mail. Um rate limiter novo (`esqueci-senha`) protege ambos os endpoints.

**Tech Stack:** Laravel 11, PHPUnit (`Tests\TestCase`), Sanctum, `Illuminate\Notifications`, `Illuminate\Support\Facades\RateLimiter`.

## Global Constraints

- Senha: `required`, `min:6`, `regex:/^[0-9]+$/` (mesma regra de `UpdateUsuarioRequest`) — copiado verbatim da spec.
- Resposta de `POST /api/auth/esqueci-senha` é **sempre idêntica** (200, mesma mensagem) independentemente de o e-mail existir, estar inativo, ou não ter e-mail — anti-enumeração de contas.
- Token expira em 60 minutos (`created_at` na tabela `password_reset_tokens`).
- Token é uso único: apagado da tabela após reset bem-sucedido.
- Reset revoga **todos** os tokens Sanctum do usuário (`$usuario->tokens()->delete()`).
- Nova env `APP_FRONTEND_URL`, fallback `http://localhost:5173` em dev.
- Fora de escopo: broker nativo do Laravel, mailer de produção, frontend, corrigir CLAUDE.md (já feito fora deste plano).

---

## Mapa de arquivos

- **Modify:** `routes/api.php` — duas rotas novas em `Route::prefix('auth')`.
- **Modify:** `app/Http/Controllers/Api/AuthController.php` — métodos `esqueciSenha` e `redefinirSenha`.
- **Create:** `app/Http/Requests/Auth/EsqueciSenhaRequest.php`
- **Create:** `app/Http/Requests/Auth/RedefinirSenhaRequest.php`
- **Create:** `app/Notifications/RedefinicaoSenhaNotification.php`
- **Modify:** `app/Providers/AppServiceProvider.php` — novo `RateLimiter::for('esqueci-senha', ...)`.
- **Modify:** `.env` e `.env.example` — `APP_FRONTEND_URL`.
- **Modify:** `config/app.php` — `'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost:5173')`.
- **Create:** `tests/Feature/Auth/ResetSenhaTest.php`

---

### Task 1: Config, env e rate limiter

**Files:**
- Modify: `config/app.php`
- Modify: `.env`
- Modify: `.env.example`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Produces: `config('app.frontend_url')` (string), rate limiter nomeado `esqueci-senha` utilizável via middleware `throttle:esqueci-senha`.

- [ ] **Step 1: Adicionar `frontend_url` em `config/app.php`**

Abra `config/app.php` e adicione, dentro do array retornado (ao lado de outras chaves de nível superior como `'url'`):

```php
    'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost:5173'),
```

- [ ] **Step 2: Adicionar a env var em `.env` e `.env.example`**

Em ambos os arquivos, logo abaixo da linha `APP_URL=...`, adicionar:

```
APP_FRONTEND_URL=http://localhost:5173
```

- [ ] **Step 3: Adicionar o rate limiter `esqueci-senha`**

Em `app/Providers/AppServiceProvider.php`, dentro de `boot()`, logo após o bloco `RateLimiter::for('login-ad', ...)` (linha 54), adicionar:

```php
        // Usado por POST /api/auth/esqueci-senha e /api/auth/redefinir-senha.
        // Chaveado por IP + e-mail submetido para não deixar um atacante
        // esgotar as tentativas de todos os e-mails de uma vez via um único IP,
        // nem permitir que ele martele um e-mail específico trocando de IP sem limite.
        RateLimiter::for('esqueci-senha', function (Request $request) {
            return Limit::perMinutes(15, 5)->by($request->ip().'|'.$request->input('email'));
        });
```

- [ ] **Step 4: Verificar que a aplicação ainda sobe sem erro de config**

Run: `php artisan config:clear && php artisan about --only=environment`
Expected: comando roda sem exceção (confirma que `config/app.php` não tem erro de sintaxe).

- [ ] **Step 5: Commit**

```bash
git add config/app.php .env.example app/Providers/AppServiceProvider.php
git commit -m "feat: adiciona config de frontend_url e rate limiter esqueci-senha"
```

(Note: `.env` normalmente é gitignored — só adicione se `git status` mostrar que está versionado neste repo; caso contrário, edite localmente sem commitar.)

---

### Task 2: Notification de redefinição de senha

**Files:**
- Create: `app/Notifications/RedefinicaoSenhaNotification.php`

**Interfaces:**
- Consumes: nada de tasks anteriores diretamente (usa `config('app.frontend_url')` da Task 1).
- Produces: `new RedefinicaoSenhaNotification(string $token, string $email)` — notification `ShouldQueue`, canal `mail`. Usada na Task 3.

- [ ] **Step 1: Criar a notification**

Crie `app/Notifications/RedefinicaoSenhaNotification.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RedefinicaoSenhaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $token, protected string $email) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')
            .'/redefinir-senha?token='.$this->token.'&email='.urlencode($this->email);

        return (new MailMessage)
            ->subject('Redefinição de senha — FleetCore')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir sua senha.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em 60 minutos.')
            ->line('Se você não solicitou isso, ignore este e-mail — nenhuma alteração será feita.');
    }
}
```

- [ ] **Step 2: Verificar sintaxe**

Run: `php -l app/Notifications/RedefinicaoSenhaNotification.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/RedefinicaoSenhaNotification.php
git commit -m "feat: adiciona notification de redefinicao de senha"
```

---

### Task 3: FormRequests de validação

**Files:**
- Create: `app/Http/Requests/Auth/EsqueciSenhaRequest.php`
- Create: `app/Http/Requests/Auth/RedefinirSenhaRequest.php`

**Interfaces:**
- Produces: `EsqueciSenhaRequest::validated()` → `['email' => string]`. `RedefinirSenhaRequest::validated()` → `['email' => string, 'token' => string, 'senha' => string]`. Usados na Task 4.

- [ ] **Step 1: Criar `EsqueciSenhaRequest`**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class EsqueciSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }
}
```

- [ ] **Step 2: Criar `RedefinirSenhaRequest`**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RedefinirSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'token' => 'required|string',
            'senha' => ['required', 'string', 'min:6', 'regex:/^[0-9]+$/'],
        ];
    }
}
```

- [ ] **Step 3: Verificar sintaxe**

Run: `php -l app/Http/Requests/Auth/EsqueciSenhaRequest.php && php -l app/Http/Requests/Auth/RedefinirSenhaRequest.php`
Expected: `No syntax errors detected` para ambos.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/Auth/EsqueciSenhaRequest.php app/Http/Requests/Auth/RedefinirSenhaRequest.php
git commit -m "feat: adiciona form requests de esqueci-senha e redefinir-senha"
```

---

### Task 4: Endpoint `POST /api/auth/esqueci-senha` (TDD)

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Auth/ResetSenhaTest.php`

**Interfaces:**
- Consumes: `EsqueciSenhaRequest` (Task 3), `RedefinicaoSenhaNotification` (Task 2).
- Produces: rota `POST /api/auth/esqueci-senha`, sempre `200 { "message": "Se o e-mail informado estiver cadastrado, você receberá instruções para redefinir sua senha." }`.

- [ ] **Step 1: Escrever os testes falhando**

Crie `tests/Feature/Auth/ResetSenhaTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use App\Notifications\RedefinicaoSenhaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResetSenhaTest extends TestCase
{
    public function test_esqueci_senha_com_email_existente_e_ativo_envia_notificacao_e_grava_token(): void
    {
        Notification::fake();

        $usuario = Usuario::factory()->create([
            'email' => 'operador@example.com',
            'ativo' => true,
        ]);

        $this->postJson('/api/auth/esqueci-senha', ['email' => 'operador@example.com'])
            ->assertOk()
            ->assertJson(['message' => 'Se o e-mail informado estiver cadastrado, você receberá instruções para redefinir sua senha.']);

        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'operador@example.com']);

        Notification::assertSentTo($usuario, RedefinicaoSenhaNotification::class);
    }

    public function test_esqueci_senha_com_email_inexistente_retorna_mesma_mensagem_e_nao_grava_nada(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/esqueci-senha', ['email' => 'naoexiste@example.com'])
            ->assertOk()
            ->assertJson(['message' => 'Se o e-mail informado estiver cadastrado, você receberá instruções para redefinir sua senha.']);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_esqueci_senha_com_usuario_inativo_retorna_mesma_mensagem_e_nao_grava_nada(): void
    {
        Notification::fake();

        Usuario::factory()->create([
            'email' => 'inativo@example.com',
            'ativo' => false,
        ]);

        $this->postJson('/api/auth/esqueci-senha', ['email' => 'inativo@example.com'])
            ->assertOk()
            ->assertJson(['message' => 'Se o e-mail informado estiver cadastrado, você receberá instruções para redefinir sua senha.']);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_esqueci_senha_sem_email_retorna_422(): void
    {
        $this->postJson('/api/auth/esqueci-senha', [])
            ->assertStatus(422);
    }

    public function test_esqueci_senha_repetido_sobrescreve_token_anterior(): void
    {
        Notification::fake();

        Usuario::factory()->create(['email' => 'operador2@example.com', 'ativo' => true]);

        $this->postJson('/api/auth/esqueci-senha', ['email' => 'operador2@example.com'])->assertOk();
        $primeiroToken = DB::table('password_reset_tokens')->where('email', 'operador2@example.com')->value('token');

        $this->postJson('/api/auth/esqueci-senha', ['email' => 'operador2@example.com'])->assertOk();
        $segundoToken = DB::table('password_reset_tokens')->where('email', 'operador2@example.com')->value('token');

        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertNotSame($primeiroToken, $segundoToken);
    }
}
```

- [ ] **Step 2: Rodar os testes e confirmar que falham (rota não existe)**

Run: `php artisan test tests/Feature/Auth/ResetSenhaTest.php`
Expected: FAIL — 404/route not found nos 5 testes.

- [ ] **Step 3: Adicionar a rota**

Em `routes/api.php`, dentro de `Route::prefix('auth')->group(function () { ... })`, logo após a linha `Route::post('login-ad', ...)` (linha 33) e antes do `Route::middleware('auth:sanctum')`:

```php
    Route::post('esqueci-senha', [AuthController::class, 'esqueciSenha'])->middleware('throttle:esqueci-senha');
    Route::post('redefinir-senha', [AuthController::class, 'redefinirSenha'])->middleware('throttle:esqueci-senha');
```

- [ ] **Step 4: Implementar `AuthController::esqueciSenha`**

Em `app/Http/Controllers/Api/AuthController.php`, adicionar os imports no topo:

```php
use App\Http\Requests\Auth\EsqueciSenhaRequest;
use App\Http\Requests\Auth\RedefinirSenhaRequest;
use App\Notifications\RedefinicaoSenhaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
```

Adicionar o método na classe (após `loginAd`, antes de `me`):

```php
    public function esqueciSenha(EsqueciSenhaRequest $r)
    {
        $email = $r->validated()['email'];

        $usuario = Usuario::where('ativo', true)->where('email', $email)->first();

        if ($usuario) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $usuario->notify(new RedefinicaoSenhaNotification($token, $email));
        }

        return response()->json([
            'message' => 'Se o e-mail informado estiver cadastrado, você receberá instruções para redefinir sua senha.',
        ]);
    }
```

- [ ] **Step 5: Rodar os testes deste endpoint e confirmar que passam**

Run: `php artisan test tests/Feature/Auth/ResetSenhaTest.php --filter=esqueci_senha`
Expected: PASS nos 5 testes de `esqueci_senha`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/Auth/ResetSenhaTest.php
git commit -m "feat: adiciona endpoint POST /api/auth/esqueci-senha"
```

---

### Task 5: Endpoint `POST /api/auth/redefinir-senha` (TDD)

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `tests/Feature/Auth/ResetSenhaTest.php`

**Interfaces:**
- Consumes: `RedefinirSenhaRequest` (Task 3), tabela `password_reset_tokens` populada pela Task 4.
- Produces: rota `POST /api/auth/redefinir-senha`, `200 { "message": "Senha redefinida com sucesso." }` em sucesso, `422` em token inválido/expirado.

- [ ] **Step 1: Adicionar os testes falhando**

Adicione ao final da classe `ResetSenhaTest` (antes do `}` final):

```php
    public function test_redefinir_senha_com_token_valido_atualiza_senha_e_revoga_tokens_sanctum(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'reset1@example.com',
            'senha_hash' => \Illuminate\Support\Facades\Hash::make('senhaantiga123'),
            'ativo' => true,
        ]);
        $usuario->createToken('app')->plainTextToken;

        Notification::fake();
        $this->postJson('/api/auth/esqueci-senha', ['email' => 'reset1@example.com'])->assertOk();

        Notification::assertSentTo($usuario, RedefinicaoSenhaNotification::class, function ($notification) use ($usuario) {
            $response = $this->postJson('/api/auth/redefinir-senha', [
                'email' => $usuario->email,
                'token' => $notification->toMail($usuario)->actionUrl
                    ? $this->extrairTokenDaUrl($notification->toMail($usuario)->actionUrl)
                    : null,
                'senha' => '654321',
            ])->assertOk()->assertJson(['message' => 'Senha redefinida com sucesso.']);

            return true;
        });

        $usuario->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('654321', $usuario->senha_hash));
        $this->assertSame(0, $usuario->tokens()->count());
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_redefinir_senha_com_token_invalido_retorna_422(): void
    {
        Usuario::factory()->create(['email' => 'reset2@example.com', 'ativo' => true]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset2@example.com',
            'token' => \Illuminate\Support\Facades\Hash::make('token-correto'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/auth/redefinir-senha', [
            'email' => 'reset2@example.com',
            'token' => 'token-errado',
            'senha' => '654321',
        ])->assertStatus(422);
    }

    public function test_redefinir_senha_com_token_expirado_retorna_422(): void
    {
        Usuario::factory()->create(['email' => 'reset3@example.com', 'ativo' => true]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset3@example.com',
            'token' => \Illuminate\Support\Facades\Hash::make('token-valido'),
            'created_at' => now()->subMinutes(61),
        ]);

        $this->postJson('/api/auth/redefinir-senha', [
            'email' => 'reset3@example.com',
            'token' => 'token-valido',
            'senha' => '654321',
        ])->assertStatus(422);
    }

    public function test_redefinir_senha_com_senha_invalida_retorna_422(): void
    {
        Usuario::factory()->create(['email' => 'reset4@example.com', 'ativo' => true]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset4@example.com',
            'token' => \Illuminate\Support\Facades\Hash::make('token-valido'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/auth/redefinir-senha', [
            'email' => 'reset4@example.com',
            'token' => 'token-valido',
            'senha' => 'abc',
        ])->assertStatus(422);
    }

    public function test_redefinir_senha_reaproveitando_token_ja_usado_falha(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'reset5@example.com', 'ativo' => true]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset5@example.com',
            'token' => \Illuminate\Support\Facades\Hash::make('token-valido'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/auth/redefinir-senha', [
            'email' => 'reset5@example.com',
            'token' => 'token-valido',
            'senha' => '654321',
        ])->assertOk();

        $this->postJson('/api/auth/redefinir-senha', [
            'email' => 'reset5@example.com',
            'token' => 'token-valido',
            'senha' => '111111',
        ])->assertStatus(422);
    }

    private function extrairTokenDaUrl(string $url): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        return $params['token'];
    }
```

- [ ] **Step 2: Rodar os testes e confirmar que falham**

Run: `php artisan test tests/Feature/Auth/ResetSenhaTest.php`
Expected: FAIL nos 5 testes novos de `redefinir_senha` (método/rota não existe).

- [ ] **Step 3: Implementar `AuthController::redefinirSenha`**

Adicionar o método na classe, logo após `esqueciSenha`:

```php
    public function redefinirSenha(RedefinirSenhaRequest $r)
    {
        $dados = $r->validated();

        $registro = DB::table('password_reset_tokens')->where('email', $dados['email'])->first();

        if (! $registro
            || now()->diffInMinutes($registro->created_at) > 60
            || ! Hash::check($dados['token'], $registro->token)
        ) {
            return response()->json(['error' => 'Token inválido ou expirado'], 422);
        }

        $usuario = Usuario::where('ativo', true)->where('email', $dados['email'])->first();

        if (! $usuario) {
            return response()->json(['error' => 'Token inválido ou expirado'], 422);
        }

        $usuario->update(['senha_hash' => Hash::make($dados['senha'])]);
        $usuario->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $dados['email'])->delete();

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
```

- [ ] **Step 4: Rodar todos os testes do arquivo e confirmar que passam**

Run: `php artisan test tests/Feature/Auth/ResetSenhaTest.php`
Expected: PASS em todos os 10 testes.

- [ ] **Step 5: Rodar a suíte completa de Auth para checar regressão**

Run: `php artisan test tests/Feature/Auth/`
Expected: PASS em `AuthTest.php` e `ResetSenhaTest.php` (nenhuma quebra no login existente).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php tests/Feature/Auth/ResetSenhaTest.php
git commit -m "feat: adiciona endpoint POST /api/auth/redefinir-senha"
```

---

### Task 6: Teste de rate limiting

**Files:**
- Modify: `tests/Feature/Auth/ResetSenhaTest.php`

**Interfaces:**
- Consumes: rota `POST /api/auth/esqueci-senha` (Task 4) e o limiter `esqueci-senha` (Task 1).

- [ ] **Step 1: Escrever o teste falhando (se o limiter não estiver registrado, este teste passaria incorretamente — então primeiro confirme que falha por ausência de 429 se você comentar o limiter; caso contrário, prossiga)**

Adicione ao final da classe `ResetSenhaTest`:

```php
    public function test_esqueci_senha_apos_exceder_limite_retorna_429(): void
    {
        Notification::fake();

        Usuario::factory()->create(['email' => 'limite@example.com', 'ativo' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/esqueci-senha', ['email' => 'limite@example.com'])->assertOk();
        }

        $this->postJson('/api/auth/esqueci-senha', ['email' => 'limite@example.com'])
            ->assertStatus(429);
    }
```

- [ ] **Step 2: Rodar o teste**

Run: `php artisan test tests/Feature/Auth/ResetSenhaTest.php --filter=test_esqueci_senha_apos_exceder_limite_retorna_429`
Expected: PASS (o limiter já foi registrado na Task 1 e a rota já usa `throttle:esqueci-senha` desde a Task 4).

- [ ] **Step 3: Rodar a suíte completa do arquivo de novo**

Run: `php artisan test tests/Feature/Auth/ResetSenhaTest.php`
Expected: PASS em todos os 11 testes.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Auth/ResetSenhaTest.php
git commit -m "test: cobre rate limiting do fluxo de reset de senha"
```

---

### Task 7: Verificação final e Pint

**Files:**
- Nenhum arquivo novo — apenas verificação.

- [ ] **Step 1: Rodar Pint nos arquivos tocados**

Run: `./vendor/bin/pint app/Http/Controllers/Api/AuthController.php app/Http/Requests/Auth/EsqueciSenhaRequest.php app/Http/Requests/Auth/RedefinirSenhaRequest.php app/Notifications/RedefinicaoSenhaNotification.php app/Providers/AppServiceProvider.php routes/api.php tests/Feature/Auth/ResetSenhaTest.php config/app.php`
Expected: roda sem erro; se reformatar algo, revisar o diff.

- [ ] **Step 2: Rodar a suíte completa de testes**

Run: `php artisan test`
Expected: PASS em toda a suíte (nenhuma regressão em outras áreas).

- [ ] **Step 3: Commit se o Pint alterou algo**

```bash
git add -A
git commit -m "style: aplica pint no fluxo de reset de senha"
```
(Pule este passo se não houver alterações.)

---

## Nota de produção (não é uma task — apenas documentar)

`MAIL_MAILER=log` está ativo hoje; os e-mails de reset cairão no log até que um mailer real seja configurado em produção (fora de escopo deste plano — mencionar no `deploy.sh`/checklist de produção quando essa funcionalidade for para produção).
