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
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Имя БД в PostgreSQL
            $table->string('username'); // Логин для подключения (берем из event_accounts.login)
            $table->text('password'); // Пароль для подключения (берем из event_accounts.password)
            
            // Внешние ключи
            $table->foreignId('server_id')
                ->nullable()
                ->constrained('servers')
                ->onDelete('set null')
                ->comment('Сервер PostgreSQL');
                
            $table->foreignId('type_id')
                ->nullable()
                ->constrained('types')
                ->onDelete('set null')
                ->comment('Тип БД (PostgreSQL)');
                
            $table->foreignId('event_account_id')
                ->constrained('event_accounts')
                ->onDelete('cascade')
                ->comment('Участник мероприятия');
                
            $table->foreignId('module_id')
                ->constrained('modules')
                ->onDelete('cascade')
                ->comment('Модуль, для которого создана БД');
                
            $table->foreignId('status_id')
                ->nullable()
                ->constrained('statuses')
                ->onDelete('set null')
                ->comment('Статус БД');
            
            // Флаги
            $table->boolean('is_active')->default(true)->comment('Активна ли БД');
            $table->boolean('is_public')->default(false)->comment('Публичная ли БД (для всех участников)');
            $table->boolean('has_demo_data')->default(false)->comment('Есть ли демо-данные');
            $table->boolean('is_empty')->default(true)->comment('Пустая ли БД (без таблиц)');
            
            // Метаданные
            $table->json('metadata')->nullable()->comment('Дополнительная информация');
            
            // Временные метки
            $table->timestamps();
            
            // Индексы для оптимизации
            $table->index(['name']);
            $table->index(['server_id', 'name']);
            $table->index(['module_id', 'event_account_id']);
            $table->index(['status_id', 'is_active']);
            $table->index(['created_at']);
            
            // Уникальность: имя БД должно быть уникальным на сервере
            $table->unique(['server_id', 'name'], 'databases_server_name_unique');
            
            // Уникальность: у одного участника в модуле может быть только одна БД
            $table->unique(['module_id', 'event_account_id'], 'databases_module_participant_unique');
        });
        
        // Добавим комментарии к таблице (опционально, но полезно)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE databases IS 'Базы данных PostgreSQL для участников мероприятий'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
