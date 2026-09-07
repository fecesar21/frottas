<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_veiculo_respostas', function (Blueprint $table) {
            $table->integer('valor')->nullable()->after('observacao');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_veiculo_respostas', function (Blueprint $table) {
            $table->dropColumn('valor');
        });
    }
};
