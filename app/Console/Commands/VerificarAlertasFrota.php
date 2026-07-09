<?php

namespace App\Console\Commands;

use App\Models\Motorista;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Notifications\CnhVencendoNotification;
use App\Notifications\ManutencaoPendenteNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class VerificarAlertasFrota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alertas:verificar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica CNHs a vencer e veículos com manutenção pendente, notificando admins/gestores';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $usuarios = Usuario::whereIn('perfil', ['admin', 'gestor'])->get();

        if ($usuarios->isEmpty()) {
            $this->info('Nenhum admin/gestor cadastrado para notificar.');

            return self::SUCCESS;
        }

        $motoristas = Motorista::where('status', 'ativo')
            ->get()
            ->filter(fn (Motorista $m) => $m->dias_para_vencer_cnh <= 30 && $m->dias_para_vencer_cnh >= 0);

        foreach ($motoristas as $motorista) {
            Notification::send($usuarios, new CnhVencendoNotification($motorista));
        }

        $veiculos = Veiculo::all()->filter(fn (Veiculo $v) => $v->precisa_manutencao);

        foreach ($veiculos as $veiculo) {
            Notification::send($usuarios, new ManutencaoPendenteNotification($veiculo));
        }

        $this->info("Alertas verificados: {$motoristas->count()} CNH(s) vencendo, {$veiculos->count()} veículo(s) em manutenção pendente.");

        return self::SUCCESS;
    }
}
