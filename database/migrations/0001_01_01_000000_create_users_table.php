<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('min_points_for_auto_upgrade')->nullable(); // optional auto-upgrade by points
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->enum('role', ['user', 'admin'])->default('user');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->foreignId('user_group_id')->nullable()->constrained('user_groups')->nullOnDelete();

            // Wallet & points — always change through WalletService / PointService
            $table->decimal('balance', 15, 2)->default(0);
            $table->unsignedBigInteger('points')->default(0);

            // Referral
            $table->string('referral_code', 16)->unique();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();

            // Security
            $table->text('two_factor_secret')->nullable();       // encrypted Google2FA secret
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->string('preferred_locale', 5)->default('en');
            $table->string('preferred_currency', 3)->default('MMK');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Email OTP codes (login / register / sensitive actions)
        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 30)->default('login');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        // "Trust this device?" — skip OTP on remembered devices
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('login_otps');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_groups');
    }
};
