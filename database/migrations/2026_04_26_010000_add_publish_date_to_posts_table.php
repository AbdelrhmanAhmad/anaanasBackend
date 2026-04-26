<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'publish_date')) {
                $table->timestamp('publish_date')->nullable()->after('created_at');
                $table->index('publish_date', 'posts_publish_date_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'publish_date')) {
                $table->dropIndex('posts_publish_date_idx');
                $table->dropColumn('publish_date');
            }
        });
    }
};
