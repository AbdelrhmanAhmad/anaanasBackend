<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('mobile', 'users_mobile_idx');
            $table->index('try_login_in_new_system', 'users_try_login_new_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            // Speeds user feeds and large import checks.
            $table->index(['user_id', 'deleted_at', 'id'], 'posts_user_deleted_id_idx');
            // Speeds section/category listing queries.
            $table->index(['section_id', 'deleted_at', 'id'], 'posts_section_deleted_id_idx');
            $table->index(['category_id', 'deleted_at', 'id'], 'posts_category_deleted_id_idx');
        });

        Schema::table('comments', function (Blueprint $table) {
            // Speeds replies loading and moderation/import checks.
            $table->index('parent_id', 'comments_parent_idx');
            $table->index(['post_id', 'parent_id', 'id'], 'comments_post_parent_id_idx');
            $table->index(['user_id', 'post_id'], 'comments_user_post_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_user_post_idx');
            $table->dropIndex('comments_post_parent_id_idx');
            $table->dropIndex('comments_parent_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_category_deleted_id_idx');
            $table->dropIndex('posts_section_deleted_id_idx');
            $table->dropIndex('posts_user_deleted_id_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_try_login_new_idx');
            $table->dropIndex('users_mobile_idx');
        });
    }
};

