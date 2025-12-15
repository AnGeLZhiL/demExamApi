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
        Schema::table('users', function (Blueprint $table) {
            // Добавляем связь с системными ролями
            $table->foreignId('system_role_id')->nullable()
                  ->constrained('system_roles')
                  ->onDelete('set null');
            
            // Старое role_id оставляем для обратной совместимости
            // Это теперь будет общая/дефолтная роль пользователя
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['system_role_id']);
            $table->dropColumn('system_role_id');
        });
    }
};
