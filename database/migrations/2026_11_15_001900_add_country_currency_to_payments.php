<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-country payment methods (PromptPay, Alipay, BCEL One, MoMo…).
 * A method has its own currency and a rate: how many Ks one unit is worth.
 * The request keeps the currency and rate used, so later rate changes
 * never change what an old top-up was worth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('code')->index(); // null = shown in every country
            $table->char('currency', 3)->default('MMK')->after('country');
            $table->decimal('rate', 14, 4)->default(1)->after('currency'); // Ks per 1 unit of currency
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->char('currency', 3)->default('MMK')->after('amount');
            $table->decimal('rate', 14, 4)->default(1)->after('currency');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('preferred_locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('country'));
        Schema::table('payment_requests', fn (Blueprint $t) => $t->dropColumn(['currency', 'rate']));
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn(['country', 'currency', 'rate']);
        });
    }
};
