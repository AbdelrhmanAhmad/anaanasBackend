<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('forbidden_words', 'locale')) {
            return;
        }

        Schema::table('forbidden_words', function (Blueprint $table) {
            $table->dropUnique(['word', 'locale', 'category']);
            $table->dropIndex(['is_active', 'locale']);
            $table->dropColumn('locale');
            $table->unique(['word', 'category']);
        });

        if (! $this->indexExists('forbidden_words', 'forbidden_words_is_active_index')) {
            Schema::table('forbidden_words', function (Blueprint $table) {
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forbidden_words', 'locale')) {
            return;
        }

        Schema::table('forbidden_words', function (Blueprint $table) {
            $table->dropUnique(['word', 'category']);
            $table->string('locale', 8)->default('any')->after('word');
            $table->index(['is_active', 'locale']);
            $table->unique(['word', 'locale', 'category']);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $result = $connection->select(
                'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?',
                [$index]
            );

            return count($result) > 0;
        }

        return false;
    }
};
