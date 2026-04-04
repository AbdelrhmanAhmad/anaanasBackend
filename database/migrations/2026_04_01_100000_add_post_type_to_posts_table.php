<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'post_type')) {
                $table->string('post_type')->default('listing')->after('status');
                $table->index('post_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'post_type')) {
                $table->dropIndex(['post_type']);
                $table->dropColumn('post_type');
            }
        });
    }
};

