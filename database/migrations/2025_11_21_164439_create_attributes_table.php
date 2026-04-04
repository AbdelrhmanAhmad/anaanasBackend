<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('input_type')->index();
            $table->string('key_name')->index();
            $table->boolean('required')->index()->default(false);
            $table->boolean('filterable')->index()->default(false);
            $table->unsignedBigInteger('parent_option_id') ->nullable()->index();
            $table->boolean('multiselect')->default(false);
            $table->unsignedBigInteger('parent_id') ->nullable() ->index();
            $table->boolean('multi_level')->default(false);

            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('category_id')->constrained('categories');
            $table->integer('sort')->nullable();
            $table->string('slug')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
