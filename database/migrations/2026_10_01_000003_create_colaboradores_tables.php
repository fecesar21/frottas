<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OU do AD cujos usuários são os colaboradores da unidade. Quando nula,
        // a sincronização usa o base_dn da configuração.
        Schema::table('unidade_ldap_configuracoes', function (Blueprint $table) {
            $table->string('ou_colaboradores', 500)->nullable()->after('base_dn');
        });

        // Cópia local dos colaboradores do AD, sincronizada periodicamente
        // (colaboradores:sincronizar) para não depender do AD na hora da viagem.
        Schema::create('colaboradores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('ldap_guid', 64)->unique();
            $table->string('nome', 255);
            $table->string('samaccountname', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('departamento', 255)->nullable();
            $table->string('cargo', 255)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('sincronizado_at')->nullable();
            $table->timestamps();

            $table->index(['unidade_id', 'ativo']);
            $table->index('nome');
        });

        Schema::create('viagem_colaborador', function (Blueprint $table) {
            $table->foreignUuid('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->foreignUuid('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->primary(['viagem_id', 'colaborador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viagem_colaborador');
        Schema::dropIfExists('colaboradores');
        Schema::table('unidade_ldap_configuracoes', function (Blueprint $table) {
            $table->dropColumn('ou_colaboradores');
        });
    }
};
