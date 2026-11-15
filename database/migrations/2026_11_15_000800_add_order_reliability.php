<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What to do when the same user orders the same IMEI/SN again while the first is still open
        Schema::table('services', function (Blueprint $table) {
            $table->enum('duplicate_policy', ['warn', 'block', 'allow'])->default('warn')->after('allow_cancel_pending');
        });

        // Claimed by a SubmitOrderToSupplier job (stops two workers sending the same order)
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('api_submitting_at')->nullable()->after('api_attempts');
        });

        // One row per order request (form token / API REFERENCE): a repeated request returns the
        // orders of the first one instead of placing and charging again.
        Schema::create('order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('source', 10)->default('web'); // web | api
            $table->json('order_ids');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_requests');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('api_submitting_at'));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('duplicate_policy'));
    }
};
