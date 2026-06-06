<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forbidden_words', function (Blueprint $table) {
            if (Schema::hasColumn('forbidden_words', 'match_type')) {
                $table->dropColumn('match_type');
            }
            if (Schema::hasColumn('forbidden_words', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forbidden_words', function (Blueprint $table) {
            if (! Schema::hasColumn('forbidden_words', 'match_type')) {
                $table->string('match_type', 16)->default('word')->after('category');
            }
            if (! Schema::hasColumn('forbidden_words', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }
        });
    }
};
