<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_veiculo_itens_modelo', function (Blueprint $table) {
            $table->boolean('requer_valor')->default(false)->after('obrigatorio');
            $table->integer('valor_min')->nullable()->after('requer_valor');
            $table->integer('valor_max')->nullable()->after('valor_min');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_veiculo_itens_modelo', function (Blueprint $table) {
            $table->dropColumn(['requer_valor', 'valor_min', 'valor_max']);
        });
    }
};
