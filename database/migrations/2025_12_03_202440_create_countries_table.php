<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('flag')->nullable();
            $table->string('is_active')->default(false)->index();
            $table->string('banner')->nullable();
            $table->string('iso2')->nullable();
            $table->string('iso_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
