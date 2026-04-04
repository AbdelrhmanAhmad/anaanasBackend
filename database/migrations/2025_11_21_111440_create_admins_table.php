<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('mobile')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(1)->index();

            $table->text('app_authentication_secret')->nullable();

            $table->text('app_authentication_recovery_codes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('deleted_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admins');
    }
};
