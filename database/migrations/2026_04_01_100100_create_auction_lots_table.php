<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auction_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained('posts')->cascadeOnDelete();
            $table->decimal('start_price', 12, 2);
            $table->decimal('current_price', 12, 2);
            $table->decimal('min_increment', 12, 2)->default(1);
            $table->decimal('reserve_price', 12, 2)->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at');
            $table->string('status')->default('live'); // draft|live|ended|cancelled
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('bids_count')->default(0);
            $table->timestamp('last_bid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_lots');
    }
};

