<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_lot_id')->constrained('auction_lots')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('accepted');
            $table->timestamps();

            $table->index(['auction_lot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_bids');
    }
};

