<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_viagem', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 80)->unique();
            $table->string('nome', 120)->unique();
            $table->enum('tipo_veiculo', ['administrativo', 'ambulancia', 'ambos']);
            $table->boolean('disponivel_solicitacao')->default(false);
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });

        $agora = now();
        $motivos = [
            ['transferencia_paciente', 'Transferência de Paciente', 'ambulancia', true],
            ['tfd', 'TFD', 'ambulancia', true],
            ['buscar_medico', 'Buscar Médico em Outra Cidade', 'administrativo', true],
            ['material_outro_hospital', 'Levar Material em Outro Hospital', 'administrativo', true],
            ['transporte_colaborador', 'Transporte de Colaborador(es)', 'administrativo', true],
            ['buscar_material_fornecedor', 'Buscar Materiais em Fornecedor', 'administrativo', true],
            ['alimentacao', 'Alimentação (Levar/Buscar)', 'administrativo', false],
            ['servicos_administrativos', 'Serviços Administrativos Diversos', 'administrativo', false],
        ];

        DB::table('motivos_viagem')->insert(array_map(fn ($m) => [
            'id' => (string) Str::uuid(), 'codigo' => $m[0], 'nome' => $m[1], 'tipo_veiculo' => $m[2],
            'disponivel_solicitacao' => $m[3], 'ativo' => true, 'sistema' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ], $motivos));
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos_viagem');
    }
};
