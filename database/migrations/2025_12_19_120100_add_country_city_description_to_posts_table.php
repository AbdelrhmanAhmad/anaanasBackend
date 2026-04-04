<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'description')) {
                $table->text('description')->nullable()->after('title');
            }

            if (!Schema::hasColumn('posts', 'country_id')) {
                // Keep it nullable to support existing rows, and avoid adding FK if countries table isn't ready.
                $table->unsignedBigInteger('country_id')->nullable()->after('category_id')->index();
            }

            if (!Schema::hasColumn('posts', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->after('country_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'city_id')) {
                $table->dropColumn('city_id');
            }
            if (Schema::hasColumn('posts', 'country_id')) {
                $table->dropColumn('country_id');
            }
            if (Schema::hasColumn('posts', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};


