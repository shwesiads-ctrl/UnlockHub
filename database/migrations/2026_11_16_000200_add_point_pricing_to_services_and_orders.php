<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('price_currency', 10)->default('ks')->after('pricing_mode');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('price_currency', 10)->default('ks')->after('price');
            $table->unsignedInteger('price_rate')->nullable()->after('price_currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['price_currency', 'price_rate']));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('price_currency'));
    }
};
