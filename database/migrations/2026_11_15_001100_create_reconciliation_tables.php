<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete(); // null = scheduler
            $table->unsignedInteger('users_checked')->default(0);
            $table->unsignedInteger('transactions_checked')->default(0);
            $table->unsignedInteger('issues_found')->default(0);
            $table->unsignedInteger('new_issues')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Problems found. Never fixed automatically: a person proposes a correction, another approves it.
        Schema::create('reconciliation_issues', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 120)->index();     // same problem across runs
            $table->foreignId('first_run_id')->nullable()->constrained('reconciliation_runs')->nullOnDelete();
            $table->foreignId('last_run_id')->nullable()->constrained('reconciliation_runs')->nullOnDelete();
            $table->string('type', 40);
            $table->enum('severity', ['high', 'medium', 'low'])->default('high');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');                 // order, payment_request, wallet transaction…
            $table->decimal('expected', 15, 2)->nullable();
            $table->decimal('actual', 15, 2)->nullable();
            $table->string('message', 255);
            // open → proposed → corrected | ignored ; cleared = disappeared by itself
            $table->enum('status', ['open', 'proposed', 'corrected', 'ignored', 'cleared'])->default('open');
            $table->enum('correction', ['credit', 'debit', 'sync_balance'])->nullable();
            $table->decimal('correction_amount', 15, 2)->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_issues');
        Schema::dropIfExists('reconciliation_runs');
    }
};
