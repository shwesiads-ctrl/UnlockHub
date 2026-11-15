<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supplier connections (DHRU Fusion API compatible)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver', 30)->default('dhru');      // dhru | (future drivers)
            $table->string('api_url')->nullable();
            $table->string('api_username')->nullable();
            $table->text('api_key')->nullable();                 // encrypted cast
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 4)->default(1); // 1 supplier currency = X MMK
            $table->decimal('balance', 15, 4)->nullable();
            $table->enum('status', ['active', 'disabled', 'error'])->default('active');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // Service groups shown on the pricing page (Samsung, iPhone · USA Carrier, Tool Activations…)
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['imei', 'server', 'remote']);
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['imei', 'server', 'remote']);
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('delivery_time')->nullable();         // "1-7 Days"

            $table->decimal('cost_price', 15, 2)->default(0);    // MMK
            $table->decimal('default_price', 15, 2)->default(0); // MMK, used when a group has no price
            $table->unsignedInteger('points')->nullable();       // null = global points rule

            $table->boolean('is_hot')->default(false);
            $table->boolean('allow_bulk')->default(false);
            $table->unsignedSmallInteger('max_bulk')->default(100);
            $table->boolean('allow_cancel_pending')->default(false);
            $table->enum('status', ['active', 'hidden', 'disabled'])->default('active');

            // Processing: manual queue or supplier API (with optional fallback to manual)
            $table->enum('processing_mode', ['manual', 'api'])->default('manual');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_service_id')->nullable();
            $table->boolean('fallback_to_manual')->default(true);

            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        // Custom order fields per service (IMEI, SN, Username, Model…)
        Schema::create('service_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('name');                              // key in order input + supplier param
            $table->enum('input_type', ['text', 'number', 'textarea', 'select', 'email']);
            $table->json('options')->nullable();                 // for select
            $table->enum('validation', ['none', 'imei', 'serial', 'email', 'regex'])->default('none');
            $table->string('regex')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_bulk_field')->default(false);    // the field split per line in bulk orders
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['service_id', 'name']);
        });

        // Reseller pricing: price per user group
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();

            $table->unique(['service_id', 'user_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('service_fields');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('suppliers');
    }
};
