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
        Schema::table('repositories', function (Blueprint $table) {
            Schema::table('repositories', function (Blueprint $table) {
            //поля для Gogs
            $table->string('ssh_url')->nullable()->after('url');
            $table->string('clone_url')->nullable()->after('ssh_url');
            $table->foreignId('status_id')
                ->nullable()
                ->after('is_active')
                ->constrained('statuses')
                ->onDelete('set null');
            $table->string('gogs_repo_id')->nullable()->after('status_id');
            $table->text('description')->nullable()->after('name');
            $table->boolean('is_private')->default(true)->after('is_active');
            $table->json('metadata')->nullable()->after('status_id');
            
            //индексы для быстрого поиска
            $table->index(['module_id', 'status_id']);
            $table->index(['event_account_id']);
            $table->index(['gogs_repo_id']);
        });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn([
                'ssh_url',
                'clone_url',
                'status_id',
                'gogs_repo_id',
                'description',
                'is_private',
                'metadata'
            ]);
            
            $table->dropIndex(['module_id', 'status_id']);
            $table->dropIndex(['event_account_id']);
            $table->dropIndex(['gogs_repo_id']);
        });
    }
};
