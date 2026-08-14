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
