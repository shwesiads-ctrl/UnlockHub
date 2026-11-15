<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->string('badge_color', 7)->nullable();  // #RRGGBB
            $table->string('badge_icon', 20)->nullable();  // user | star | crown | gem | shield | bolt
        });

        // Sensible defaults for the seeded groups
        foreach (['customer' => ['#64748B', 'user'], 'reseller' => ['#2459E8', 'shield'], 'vip' => ['#D4A017', 'crown']] as $slug => [$color, $icon]) {
            DB::table('user_groups')->where('slug', $slug)->update(['badge_color' => $color, 'badge_icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('user_groups', fn (Blueprint $t) => $t->dropColumn(['badge_color', 'badge_icon']));
    }
};
