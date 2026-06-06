<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forbidden_words', function (Blueprint $table) {
            $table->dropUnique(['word', 'category']);
        });

        Schema::table('forbidden_words', function (Blueprint $table) {
            $table->unique('word');
        });
    }

    public function down(): void
    {
        Schema::table('forbidden_words', function (Blueprint $table) {
            $table->dropUnique(['word']);
        });

        Schema::table('forbidden_words', function (Blueprint $table) {
            $table->unique(['word', 'category']);
        });
    }
};
