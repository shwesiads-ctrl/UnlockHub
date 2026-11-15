<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real delivery times of finished orders (placed → success), per service and per supplier
        Schema::create('service_delivery_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_key')->default(0); // 0 = all suppliers / manual together
            $table->unsignedSmallInteger('window_days');           // 7 | 30
            $table->unsignedInteger('samples');
            $table->unsignedInteger('avg_minutes');
            $table->unsignedInteger('median_minutes');
            $table->unsignedInteger('p25_minutes');
            $table->unsignedInteger('p75_minutes');
            $table->unsignedInteger('p90_minutes');
            $table->timestamp('computed_at');

            $table->unique(['service_id', 'supplier_key', 'window_days'], 'delivery_stats_unique');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('max_delivery_minutes')->nullable(); // alert admin when an open order is older
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('overdue_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('overdue_alerted_at'));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('max_delivery_minutes'));
        Schema::dropIfExists('service_delivery_stats');
    }
};
