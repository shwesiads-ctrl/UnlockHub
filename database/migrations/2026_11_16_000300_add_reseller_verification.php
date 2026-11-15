<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_profiles', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('status');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
            $table->string('verification_source', 20)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_profiles', fn (Blueprint $table) => $table->dropColumn(['is_verified', 'verified_at', 'verification_source']));
    }
};
