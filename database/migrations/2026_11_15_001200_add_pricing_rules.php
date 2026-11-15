<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pricing rule per customer group: cost + markup (percent or fixed Ks), never below min profit
        Schema::table('user_groups', function (Blueprint $table) {
            $table->enum('markup_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('markup_value', 12, 2)->default(15);
            $table->decimal('min_profit', 12, 2)->default(0);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->enum('pricing_mode', ['manual', 'auto'])->default('manual'); // auto = prices from group rules
            $table->decimal('supplier_cost', 15, 4)->nullable();                 // in the supplier's currency
            $table->timestamp('cost_updated_at')->nullable();
            $table->boolean('sales_paused')->default(false);                      // e.g. cost went above price
            $table->string('paused_reason', 255)->nullable();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->timestamp('exchange_rate_updated_at')->nullable();
        });

        // Every cost change (supplier sync, import, exchange rate, manual edit)
        Schema::create('service_cost_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('old_cost', 15, 2)->nullable();
            $table->decimal('new_cost', 15, 2);
            $table->decimal('old_supplier_cost', 15, 4)->nullable();
            $table->decimal('new_supplier_cost', 15, 4)->nullable();
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->string('source', 20); // sync | import | rate | manual
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['service_id', 'created_at']);
        });

        // Big automatic price moves wait for an admin
        Schema::create('price_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->json('old_prices');  // {"default": 1000, "groups": {"2": 900}}
            $table->json('new_prices');
            $table->decimal('old_cost', 15, 2)->nullable();
            $table->decimal('new_cost', 15, 2);
            $table->decimal('change_percent', 8, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'superseded'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_change_requests');
        Schema::dropIfExists('service_cost_changes');
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('exchange_rate_updated_at'));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn(['pricing_mode', 'supplier_cost', 'cost_updated_at', 'sales_paused', 'paused_reason']));
        Schema::table('user_groups', fn (Blueprint $t) => $t->dropColumn(['markup_type', 'markup_value', 'min_profit']));
    }
};
