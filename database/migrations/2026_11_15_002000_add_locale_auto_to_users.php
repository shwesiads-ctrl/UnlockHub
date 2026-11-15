<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * locale_auto = language follows the visitor's location until they pick one themselves.
 * Accounts that already chose something other than the old default keep their choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('locale_auto')->default(true)->after('preferred_locale');
        });

        DB::table('users')->where('preferred_locale', '!=', 'en')->update(['locale_auto' => false]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale_auto'));
    }
};
