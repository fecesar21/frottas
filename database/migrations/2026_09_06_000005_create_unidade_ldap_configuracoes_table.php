<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidade_ldap_configuracoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unidade_id')->unique()->constrained('unidades')->cascadeOnDelete();
            $table->string('host');
            $table->unsignedInteger('port')->default(636);
            $table->string('base_dn');
            $table->string('username');
            $table->text('password');
            $table->boolean('use_ssl')->default(true);
            $table->boolean('use_starttls')->default(false);
            $table->string('unidade_attribute')->default('department');
            $table->json('valores_ad');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade_ldap_configuracoes');
    }
};
