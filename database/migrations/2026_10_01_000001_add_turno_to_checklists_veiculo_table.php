<?php

use App\Support\Plantao;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklists_veiculo', function (Blueprint $table) {
            $table->string('turno', 10)->default('diurno')->after('data_referencia');
        });

        // O novo unique é criado antes de remover o antigo: no MySQL a FK de
        // veiculo_id depende de um índice iniciado por essa coluna.
        Schema::table('checklists_veiculo', function (Blueprint $table) {
            $table->unique(['veiculo_id', 'data_referencia', 'turno']);
        });

        Schema::table('checklists_veiculo', function (Blueprint $table) {
            $table->dropUnique(['veiculo_id', 'data_referencia']);
        });

        DB::table('checklists_veiculo')->orderBy('created_at')->get(['id', 'veiculo_id', 'created_at'])
            ->each(function ($row) {
                $plantao = Plantao::atual(Carbon::parse($row->created_at)->setTimezone(config('app.timezone')));

                if ($plantao['turno'] === 'diurno') {
                    return;
                }

                $existe = DB::table('checklists_veiculo')
                    ->where('veiculo_id', $row->veiculo_id)
                    ->whereDate('data_referencia', $plantao['data'])
                    ->where('turno', $plantao['turno'])
                    ->exists();

                if (! $existe) {
                    DB::table('checklists_veiculo')->where('id', $row->id)->update([
                        'data_referencia' => $plantao['data'],
                        'turno' => $plantao['turno'],
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('checklists_veiculo', function (Blueprint $table) {
            $table->unique(['veiculo_id', 'data_referencia']);
        });

        Schema::table('checklists_veiculo', function (Blueprint $table) {
            $table->dropUnique(['veiculo_id', 'data_referencia', 'turno']);
            $table->dropColumn('turno');
        });
    }
};
