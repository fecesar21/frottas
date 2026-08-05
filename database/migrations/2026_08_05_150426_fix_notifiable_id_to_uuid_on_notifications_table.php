<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // notifiable_id was created as an integer by the default morphs() call,
        // but Usuario (the only notifiable model) uses UUID primary keys.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('notifications', function ($table) {
                $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
                $table->string('notifiable_id', 36)->change();
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        } else {
            DB::statement('ALTER TABLE notifications DROP INDEX notifications_notifiable_type_notifiable_id_index');
            DB::statement('ALTER TABLE notifications MODIFY notifiable_id CHAR(36) NOT NULL');
            DB::statement('ALTER TABLE notifications ADD INDEX notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('notifications', function ($table) {
                $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
                $table->unsignedBigInteger('notifiable_id')->change();
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        } else {
            DB::statement('ALTER TABLE notifications DROP INDEX notifications_notifiable_type_notifiable_id_index');
            DB::statement('ALTER TABLE notifications MODIFY notifiable_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE notifications ADD INDEX notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id)');
        }
    }
};
