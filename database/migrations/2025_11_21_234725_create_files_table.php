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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // имя файла
            $table->string('path'); // путь к файлу на сервере
            $table->string('size')->nullable(); // размер файла
            $table->string('mime_type')->nullable(); // тип файла
            $table->foreignId('module_id')->constrained('modules');
            $table->foreignId('event_account_id')->constrained('event_accounts');
            $table->boolean('is_public')->default(true); // публичность
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
