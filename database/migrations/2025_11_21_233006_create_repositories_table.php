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
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->foreignId('server_id')
                ->constrained('servers')
                ->onDelete('set null');
            $table->foreignId('type_id')
                ->constrained('types')
                ->onDelete('set null');
            $table->foreignId('event_account_id')
                ->constrained('event_accounts')
                ->onDelete('cascade');
            $table->foreignId('module_id')
                ->constrained('modules')
                ->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
