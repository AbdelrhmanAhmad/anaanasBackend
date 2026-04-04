<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_deletion_at'); // 30 days from requested_at
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('scheduled_deletion_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};

