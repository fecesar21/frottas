<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_new', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('unidade_id')->nullable();
            $table->string('motivo');
            $table->uuid('origem_unidade_id')->nullable();
            $table->uuid('destino_unidade_id')->nullable();
            $table->unsignedInteger('numero_atendimento')->nullable();
            $table->string('cidade', 150)->nullable();
            $table->string('hospital_destino', 150)->nullable();
            $table->string('fornecedor_nome', 150)->nullable();
            $table->enum('status', ['aberto', 'em_trajeto', 'aguardando_finalizacao_trajeto', 'finalizado', 'cancelado'])->default('aberto');
            $table->uuid('viagem_id')->nullable();
            $table->uuid('motorista_pendente_id')->nullable();
            $table->uuid('veiculo_pendente_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->foreign('unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('origem_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('destino_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('viagem_id')->references('id')->on('viagens')->nullOnDelete();
            $table->foreign('motorista_pendente_id')->references('id')->on('motoristas')->nullOnDelete();
            $table->foreign('veiculo_pendente_id')->references('id')->on('veiculos')->nullOnDelete();
        });

        DB::statement('INSERT INTO solicitacoes_new (id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, status, viagem_id, observacoes, created_at, updated_at)
            SELECT id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, status, viagem_id, observacoes, created_at, updated_at FROM solicitacoes');

        Schema::drop('solicitacoes');
        Schema::rename('solicitacoes_new', 'solicitacoes');
    }

    public function down(): void
    {
        Schema::create('solicitacoes_old', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('unidade_id')->nullable();
            $table->string('motivo');
            $table->uuid('origem_unidade_id')->nullable();
            $table->uuid('destino_unidade_id')->nullable();
            $table->unsignedInteger('numero_atendimento')->nullable();
            $table->string('cidade', 150)->nullable();
            $table->string('hospital_destino', 150)->nullable();
            $table->string('fornecedor_nome', 150)->nullable();
            $table->enum('status', ['aberto', 'em_trajeto', 'finalizado', 'cancelado'])->default('aberto');
            $table->uuid('viagem_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->foreign('unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('origem_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('destino_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('viagem_id')->references('id')->on('viagens')->nullOnDelete();
        });

        DB::statement("INSERT INTO solicitacoes_old (id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, status, viagem_id, observacoes, created_at, updated_at)
            SELECT id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, CASE WHEN status = 'aguardando_finalizacao_trajeto' THEN 'em_trajeto' ELSE status END, viagem_id, observacoes, created_at, updated_at FROM solicitacoes");

        Schema::drop('solicitacoes');
        Schema::rename('solicitacoes_old', 'solicitacoes');
    }
};
