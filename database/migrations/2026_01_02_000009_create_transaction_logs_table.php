<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('cascade');
            $table->enum('transaction_type', ['booking_created', 'booking_confirmed', 'booking_rejected', 'booking_cancelled', 'payment_submitted', 'payment_verified', 'payment_rejected', 'room_added', 'room_updated', 'room_deleted']);
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('booking_id');
            $table->index('payment_id');
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
    }
};
