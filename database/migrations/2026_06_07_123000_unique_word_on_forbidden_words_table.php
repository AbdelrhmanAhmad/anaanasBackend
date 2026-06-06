<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('forbidden_words', 'forbidden_words_word_category_unique')) {
            Schema::table('forbidden_words', function (Blueprint $table) {
                $table->dropUnique(['word', 'category']);
            });
        }

        if (! $this->indexExists('forbidden_words', 'forbidden_words_word_unique')) {
            Schema::table('forbidden_words', function (Blueprint $table) {
                $table->unique('word');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('forbidden_words', 'forbidden_words_word_unique')) {
            Schema::table('forbidden_words', function (Blueprint $table) {
                $table->dropUnique(['word']);
            });
        }

        if (! $this->indexExists('forbidden_words', 'forbidden_words_word_category_unique')) {
            Schema::table('forbidden_words', function (Blueprint $table) {
                $table->unique(['word', 'category']);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $result = $connection->select(
                'SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?',
                [$index]
            );

            return count($result) > 0;
        }

        if ($driver === 'pgsql') {
            $result = $connection->select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $index]
            );

            return count($result) > 0;
        }

        return false;
    }
};
