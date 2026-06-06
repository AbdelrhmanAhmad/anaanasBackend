<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'images_local_synced')) {
                $table->boolean('images_local_synced')->default(false)->after('main_image');
                $table->index('images_local_synced');
            }

            if (! Schema::hasColumn('posts', 'images_local_synced_at')) {
                $table->timestamp('images_local_synced_at')->nullable()->after('images_local_synced');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'images_local_synced_at')) {
                $table->dropColumn('images_local_synced_at');
            }

            if (Schema::hasColumn('posts', 'images_local_synced')) {
                $table->dropIndex(['images_local_synced']);
                $table->dropColumn('images_local_synced');
            }
        });
    }
};
