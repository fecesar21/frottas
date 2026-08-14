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
}
