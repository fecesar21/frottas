<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_veiculo_respostas', function (Blueprint $table) {
            $table->json('fotos')->nullable()->after('foto_path');
        });

        DB::table('checklist_veiculo_respostas')->whereNotNull('foto_path')->orderBy('id')->each(function ($r) {
            DB::table('checklist_veiculo_respostas')->where('id', $r->id)->update(['fotos' => json_encode([$r->foto_path])]);
        });
    }

    public function down(): void
    {
        Schema::table('checklist_veiculo_respostas', function (Blueprint $table) {
            $table->dropColumn('fotos');
        });
    }
};
