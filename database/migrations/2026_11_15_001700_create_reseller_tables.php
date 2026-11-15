<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-users belong to a reseller (parent)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('referred_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('reseller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->string('shop_name', 80);
            $table->string('subdomain', 40)->nullable()->unique();
            $table->string('custom_domain', 120)->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_telegram', 60)->nullable();
            $table->string('contact_email', 120)->nullable();
            $table->text('about')->nullable();
            $table->enum('markup_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('markup_value', 12, 2)->default(10);
            $table->boolean('allow_signup')->default(true);       // customers can register on the reseller's domain
            $table->string('application_note', 500)->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Reseller's own selling price for a service (overrides the markup)
        Schema::create('reseller_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();
            $table->unique(['reseller_id', 'service_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->constrained('users')->nullOnDelete(); // sub-user's reseller
            $table->decimal('reseller_price', 15, 2)->nullable(); // what the site charges the reseller (site revenue)
            $table->boolean('reseller_paid')->default(false);     // reseller profit credited
            $table->boolean('commission_paid')->default(false);   // affiliate commission credited
            $table->index(['reseller_id', 'status']);
        });

        // More kinds of wallet movements (transfers, profit, commission, withdrawals)
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('type', 20)->change();
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 40);                  // KBZPay, WavePay, bank…
            $table->string('account', 120);                // phone / account no. + name
            $table->enum('status', ['pending', 'paid', 'rejected'])->default('pending');
            $table->string('admin_note', 255)->nullable();
            $table->string('payout_reference', 120)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reseller_id');
            $t->dropColumn(['reseller_price', 'reseller_paid', 'commission_paid']);
        });
        Schema::dropIfExists('reseller_prices');
        Schema::dropIfExists('reseller_profiles');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('parent_id'));
    }
};
