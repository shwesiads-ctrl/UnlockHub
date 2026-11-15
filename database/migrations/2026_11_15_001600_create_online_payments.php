<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Card (Stripe) / PayPal top-ups: credited automatically once the gateway confirms payment
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);                      // stripe | paypal
            $table->string('reference', 120)->nullable()->unique(); // Stripe session id / PayPal order id
            $table->decimal('amount', 12, 2);                   // charged, gateway currency (incl. fee)
            $table->decimal('fee', 12, 2)->default(0);
            $table->string('currency', 3);
            $table->decimal('rate', 12, 4);                     // Ks per 1 unit at the time
            $table->decimal('credit_ks', 15, 2);                // what the wallet gets
            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled', 'expired'])->default('pending');
            $table->string('gateway_txn', 120)->nullable();     // Stripe payment_intent / PayPal capture id
            $table->string('error', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
