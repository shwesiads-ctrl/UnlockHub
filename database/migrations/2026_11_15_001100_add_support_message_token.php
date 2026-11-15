<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->uuid('client_token')->nullable();
            $table->unique(['support_ticket_id', 'user_id', 'client_token'], 'support_message_client_unique');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropUnique('support_message_client_unique');
            $table->dropColumn('client_token');
        });
    }
};
