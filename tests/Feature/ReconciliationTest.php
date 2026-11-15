<?php

namespace Tests\Feature;

use App\Models\ReconciliationIssue;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\ReconciliationService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'finance'): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin', 'staff_role' => $role])->save();

        return $u->fresh();
    }

    private function fundedCustomer(float $amount = 10000): User
    {
        $u = $this->customer(0);
        app(WalletService::class)->credit($u, $amount, 'admin_credit', null, 'opening');

        return $u->fresh();
    }

    public function test_clean_books_have_no_issues(): void
    {
        $u = $this->fundedCustomer();
        $order = app(OrderService::class)->place($u, $this->imeiService(), [['imei' => '356938035643809']])->first();
        app(OrderService::class)->reject($order, 'Not supported');

        $run = app(ReconciliationService::class)->run();

        $this->assertSame(0, $run->issues_found);
    }

    public function test_finds_problems_but_never_changes_a_balance(): void
    {
        $u = $this->fundedCustomer();
        $order = app(OrderService::class)->place($u, $this->imeiService(), [['imei' => '356938035643809']])->first();
        app(OrderService::class)->reject($order, 'Not supported');

        // Break the books: refund row lost, balance edited by hand
        WalletTransaction::where('type', 'refund')->delete();
        $u->forceFill(['balance' => 12345])->save();

        app(ReconciliationService::class)->run();

        $types = ReconciliationIssue::pluck('type')->all();
        $this->assertContains('missing_refund', $types);
        $this->assertContains('balance_mismatch', $types);
        $this->assertEquals(12345, (float) $u->fresh()->balance); // untouched
    }

    public function test_same_problem_is_not_duplicated_across_runs(): void
    {
        $u = $this->fundedCustomer();
        $u->forceFill(['balance' => 1])->save();

        // TEMP DIAGNOSTIC
        $probe = [
            'stored' => DB::table('users')->where('id', $u->id)->value('balance'),
            'ledger' => DB::table('wallet_transactions')->where('user_id', $u->id)->sum('amount'),
            'txs' => DB::table('wallet_transactions')->select('id', 'user_id', 'type', 'amount')->get()->toArray(),
            'check' => DB::table('users')->leftJoin('wallet_transactions as t', 't.user_id', '=', 'users.id')
                ->groupBy('users.id', 'users.balance')->selectRaw('users.id, users.balance, COALESCE(SUM(t.amount),0) AS ledger')->get()->toArray(),
        ];
        $this->assertSame('', json_encode($probe, JSON_PRETTY_PRINT), 'PROBE');

        app(ReconciliationService::class)->run();
        app(ReconciliationService::class)->run();

        $this->assertSame(1, ReconciliationIssue::where('type', 'balance_mismatch')->count());
    }

    public function test_correction_needs_a_second_person_and_a_reason(): void
    {
        $u = $this->fundedCustomer(10000);
        $u->forceFill(['balance' => 9000])->save();
        $svc = app(ReconciliationService::class);
        $svc->run();
        $issue = ReconciliationIssue::where('type', 'balance_mismatch')->firstOrFail();
        $a = $this->staff('finance');
        $b = $this->staff('owner');

        $svc->propose($issue, $a, 'sync_balance', null, 'Balance was edited in the database by mistake');
        $this->assertEquals(9000, (float) $u->fresh()->balance);

        try {
            $svc->approve($issue->fresh(), $a);
            $this->fail('Proposer approved own correction');
        } catch (RuntimeException) {
        }

        $svc->approve($issue->fresh(), $b);
        $this->assertEquals(10000, (float) $u->fresh()->balance);
        $fresh = $issue->fresh();
        $this->assertSame('corrected', $fresh->status);
        $this->assertSame($a->id, $fresh->proposed_by);
        $this->assertSame($b->id, $fresh->approved_by);
    }
}
