<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('category_id')->constrained('categories');
            
            $table->foreignId('country_id')->constrained('countries');
            $table->foreignId('city_id')->constrained('cities');


            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('status')->default('active');
            $table->string('main_image')->nullable();
            $table->json('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
