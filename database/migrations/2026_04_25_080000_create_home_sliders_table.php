<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores admin-managed slides for the home banner carousel.
     * Each slide carries 4 image variants (desktop/mobile × ar/en) so the
     * frontend can pick the best-fit asset at render time.
     */
    public function up(): void
    {
        Schema::create('home_sliders', function (Blueprint $table) {
            $table->id();

            // Optional admin-facing label (not displayed to users).
            $table->string('title')->nullable();

            // Locale + breakpoint specific images stored on S3.
            $table->string('image_desktop_ar')->nullable();
            $table->string('image_desktop_en')->nullable();
            $table->string('image_mobile_ar')->nullable();
            $table->string('image_mobile_en')->nullable();

            // Optional click-through URL (absolute or relative).
            $table->string('url')->nullable();
            // Open in new tab when true.
            $table->boolean('open_in_new_tab')->default(false);

            // Optional country scoping (null = global). Avoid FK to keep deletes safe.
            $table->unsignedBigInteger('country_id')->nullable()->index();

            // Visibility window — both nullable means "always".
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sliders');
    }
};
