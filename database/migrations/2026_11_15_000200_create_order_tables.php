<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per IMEI/item. A bulk submission shares one batch_id.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 20)->unique();             // public tracking number
            $table->uuid('batch_id')->nullable()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('service_name');                       // snapshot

            $table->json('input');                                // field values
            $table->string('imei')->nullable()->index();          // main identifier for search
            $table->string('user_note')->nullable();

            $table->decimal('price', 15, 2);                      // charged to user (MMK)
            $table->decimal('cost', 15, 2)->default(0);           // cost at order time (MMK)
            $table->unsignedInteger('points')->default(0);        // to award on success

            $table->enum('status', ['pending', 'in_process', 'success', 'rejected', 'cancelled'])->default('pending');
            $table->enum('processing_mode', ['manual', 'api']);

            // API tracking
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_order_id')->nullable()->index();
            $table->unsignedTinyInteger('api_attempts')->default(0);
            $table->boolean('api_failed')->default(false);        // fell back to manual
            $table->text('api_error')->nullable();
            $table->timestamp('api_next_check_at')->nullable()->index();

            // Result
            $table->text('result')->nullable();                   // code / message shown to user
            $table->text('admin_note')->nullable();               // internal only
            $table->boolean('refunded')->default(false);
            $table->boolean('points_awarded')->default(false);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'processing_mode']);
            $table->index(['user_id', 'status']);
        });

        // Every balance change. Sum(amount) per user == users.balance
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'order', 'refund', 'points_redeem', 'admin_credit', 'admin_debit']);
            $table->decimal('amount', 15, 2);                     // + credit / - debit
            $table->decimal('balance_after', 15, 2);
            $table->nullableMorphs('reference');                  // Order, PaymentRequest…
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['order', 'referral', 'redeem', 'admin', 'reversal']);
            $table->integer('points');                            // + earn / - spend
            $table->unsignedBigInteger('points_after');
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('orders');
    }
};
