<?php

namespace App\Console\Commands;

use App\Services\ColaboradorSyncService;
use Illuminate\Console\Command;

class SincronizarColaboradores extends Command
{
    protected $signature = 'colaboradores:sincronizar';

    protected $description = 'Sincroniza os colaboradores do AD (OU de cada unidade) para a tabela local';

    public function handle(ColaboradorSyncService $service): int
    {
        $resultado = $service->sincronizarTodas();

        if ($resultado === []) {
            $this->info('Nenhuma unidade com AD configurado.');

            return self::SUCCESS;
        }

        foreach ($resultado as $unidadeId => $r) {
            $r['ok']
                ? $this->info("Unidade {$unidadeId}: {$r['sincronizados']} sincronizado(s), {$r['inativados']} inativado(s).")
                : $this->error("Unidade {$unidadeId}: falha — {$r['erro']}");
        }

        return collect($resultado)->every(fn ($r) => $r['ok']) ? self::SUCCESS : self::FAILURE;
    }
}
