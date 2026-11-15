<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\WalletAdjustment;
use App\Services\WalletAdjustmentService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StaffPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin', 'staff_role' => $role])->save();

        return $u->fresh();
    }

    public function test_blocked_pages_are_closed_by_url_not_only_hidden(): void
    {
        $support = $this->staff('support');

        $this->actingAs($support)->get('/admin/settings')->assertForbidden();
        $this->actingAs($support)->get('/admin/suppliers')->assertForbidden();
        $this->actingAs($support)->get('/admin/staff')->assertForbidden();
        $this->actingAs($support)->post('/admin/staff')->assertForbidden();
    }

    public function test_route_map(): void
    {
        $owner = $this->staff('owner');
        $finance = $this->staff('finance');
        $editor = $this->staff('editor');

        $this->assertTrue(Permissions::allowsRoute($owner, 'admin.suppliers.index'));
        $this->assertTrue(Permissions::allowsRoute($finance, 'admin.payments.approve'));
        $this->assertFalse(Permissions::allowsRoute($finance, 'admin.suppliers.edit'));
        $this->assertFalse(Permissions::allowsRoute($editor, 'admin.orders.index'));
        $this->assertTrue(Permissions::allowsRoute($editor, 'admin.posts.index'));
        // Unmapped admin route → owner only
        $this->assertFalse(Permissions::allowsRoute($finance, 'admin.something-new'));
        $this->assertTrue(Permissions::allowsRoute($owner, 'admin.something-new'));
        // Customers never
        $this->assertFalse(Permissions::allowsRoute($this->customer(), 'admin.dashboard'));
    }

    public function test_staff_permissions_are_editable_but_owner_only_stays_owner_only(): void
    {
        Setting::put('staff.permissions', json_encode(['support' => ['orders.view', 'staff.manage', 'bogus']]));

        $this->assertSame(['orders.view'], Permissions::matrix()['support']);
    }

    public function test_large_wallet_change_needs_a_second_person(): void
    {
        Setting::put('wallet.approval_threshold', '10000');
        $a = $this->staff('finance');
        $b = $this->staff('owner');
        $customer = $this->customer(0);
        $svc = app(WalletAdjustmentService::class);

        $small = $svc->request($customer, 'credit', 5000, 'cash', $a);
        $this->assertSame('approved', $small->status);
        $this->assertEquals(5000, (float) $customer->fresh()->balance);

        $big = $svc->request($customer, 'credit', 50000, 'bank transfer', $a);
        $this->assertSame('pending', $big->status);
        $this->assertEquals(5000, (float) $customer->fresh()->balance);

        try {
            $svc->approve($big, $a);
            $this->fail('Requester approved own adjustment');
        } catch (RuntimeException) {
        }

        $svc->approve($big, $b);
        $this->assertEquals(55000, (float) $customer->fresh()->balance);
        $this->assertSame('approved', WalletAdjustment::find($big->id)->status);
        $this->assertSame($b->id, WalletAdjustment::find($big->id)->reviewed_by);
    }
}
