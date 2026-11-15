<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Telegram / TikTok logins don't give an email
            $table->string('email')->nullable()->change();

            // Profile
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('shop_name', 100)->nullable()->after('avatar_path');
            $table->string('city', 60)->nullable()->after('shop_name');
            $table->boolean('password_set')->default(true)->after('password'); // false for social-only accounts
            $table->boolean('profile_bonus_awarded')->default(false)->after('points');

            // Daily check-in
            $table->date('last_checkin_on')->nullable()->after('profile_bonus_awarded');
            $table->unsignedSmallInteger('checkin_streak')->default(0)->after('last_checkin_on');

            // Reseller API (DHRU compatible)
            $table->boolean('api_enabled')->default(false)->after('checkin_streak');
            $table->string('api_key_hash', 64)->nullable()->unique()->after('api_enabled');
            $table->string('api_key_hint', 8)->nullable()->after('api_key_hash');
            $table->text('api_ips')->nullable()->after('api_key_hint'); // one IP per line, empty = any
            $table->timestamp('api_last_used_at')->nullable()->after('api_ips');
        });

        // New point types (signup, profile, topup, checkin): use a plain string
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->string('type', 20)->change();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);          // google, facebook, discord, tiktok, telegram
            $table->string('provider_id', 191);
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
            $table->dropColumn([
                'avatar_path', 'shop_name', 'city', 'password_set', 'profile_bonus_awarded',
                'last_checkin_on', 'checkin_streak',
                'api_enabled', 'api_key_hash', 'api_key_hint', 'api_ips', 'api_last_used_at',
            ]);
        });
    }
};
