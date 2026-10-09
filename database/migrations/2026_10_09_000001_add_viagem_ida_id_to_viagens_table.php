<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            // Preenchido na viagem de retorno à origem: aponta para a viagem de ida que a gerou.
            $table->foreignUuid('viagem_ida_id')->nullable()->after('checkin_id')
                ->constrained('viagens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('viagem_ida_id');
        });
    }
};
