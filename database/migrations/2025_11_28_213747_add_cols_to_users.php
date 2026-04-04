<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string("mobile")->after("name")->nullable();
            $table->string("mobile_verified_at")->after("mobile")->nullable();
            $table->string("mobile_verified")->after("mobile_verified_at")->default(false);
            $table->string("email")->nullable()->change();
        });


    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn("mobile");
            $table->dropColumn("mobile_verified_at");
            $table->dropColumn("mobile_verified");

        });
    }
};
