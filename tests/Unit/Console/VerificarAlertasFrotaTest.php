<?php

namespace Tests\Unit\Console;

use App\Models\Motorista;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Notifications\CnhVencendoNotification;
use App\Notifications\ManutencaoPendenteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificarAlertasFrotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifica_admin_e_gestor_sobre_cnh_vencendo_mas_nao_operador(): void
    {
        Notification::fake();

        $admin = Usuario::factory()->create(['perfil' => 'admin']);
        $gestor = Usuario::factory()->create(['perfil' => 'gestor']);
        $operador = Usuario::factory()->create(['perfil' => 'operador']);

        $motorista = Motorista::factory()->create([
            'status' => 'ativo',
            'cnh_validade' => now()->addDays(15)->format('Y-m-d'),
        ]);

        $this->artisan('alertas:verificar')->assertExitCode(0);

        Notification::assertSentTo($admin, CnhVencendoNotification::class);
        Notification::assertSentTo($gestor, CnhVencendoNotification::class);
        Notification::assertNotSentTo($operador, CnhVencendoNotification::class);
    }

    public function test_notifica_sobre_veiculo_com_manutencao_pendente(): void
    {
        Notification::fake();

        $admin = Usuario::factory()->create(['perfil' => 'admin']);

        Veiculo::factory()->create([
            'km_atual' => 50000,
            'km_proxima_revisao' => 40000,
        ]);

        $this->artisan('alertas:verificar')->assertExitCode(0);

        Notification::assertSentTo($admin, ManutencaoPendenteNotification::class);
    }

    public function test_nao_notifica_veiculo_sem_manutencao_pendente(): void
    {
        Notification::fake();

        $admin = Usuario::factory()->create(['perfil' => 'admin']);

        Veiculo::factory()->create([
            'km_atual' => 10000,
            'km_proxima_revisao' => 40000,
        ]);

        $this->artisan('alertas:verificar')->assertExitCode(0);

        Notification::assertNotSentTo($admin, ManutencaoPendenteNotification::class);
    }
}
