<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_accounts', function (Blueprint $table) {
            // Добавляем колонку role_id после event_id
            $table->unsignedBigInteger('role_id')->nullable()->after('event_id');
            
            // Создаем внешний ключ на таблицу roles
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_accounts', function (Blueprint $table) {
            // Удаляем внешний ключ
            $table->dropForeign(['role_id']);
            
            // Удаляем колонку
            $table->dropColumn('role_id');
        });
    }
};
