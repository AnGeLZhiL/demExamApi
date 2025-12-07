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
            // Просто добавляем поле password_plain, не переименовывая password
            $table->string('password_plain')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_accounts', function (Blueprint $table) {
            $table->dropColumn('password_plain');
        });
    }
};
