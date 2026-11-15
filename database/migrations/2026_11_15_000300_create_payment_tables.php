<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KBZPay, WavePay, AYA Pay, CB Pay… (admin-managed)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('qr_path')->nullable();
            $table->text('instructions')->nullable();
            $table->decimal('min_amount', 15, 2)->default(5000);
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // User top-up requests with receipt upload → admin approve / reject
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);                     // claimed by user
            $table->string('transaction_id', 64);
            $table->string('receipt_path');                       // private disk
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->decimal('credited_amount', 15, 2)->nullable();
            $table->string('reject_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_method_id', 'transaction_id']); // duplicate detection
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('payment_methods');
    }
};
