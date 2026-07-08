<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abastecimentos', function (Blueprint $table) {
            $table->string('comprovante_path')->nullable()->after('nota_fiscal');
        });
    }

    public function down(): void
    {
        Schema::table('abastecimentos', function (Blueprint $table) {
            $table->dropColumn('comprovante_path');
        });
    }
};
