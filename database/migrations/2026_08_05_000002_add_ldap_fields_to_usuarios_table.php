<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('ldap_guid')->nullable()->unique()->after('cpf');
            $table->timestamp('ldap_sync_at')->nullable()->after('ldap_guid');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('senha_hash')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('senha_hash')->nullable(false)->change();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['ldap_guid']);
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['ldap_guid', 'ldap_sync_at']);
        });
    }
};
