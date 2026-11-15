<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_favourite_toggle(): void
    {
        $user = $this->customer();
        $service = $this->imeiService();

        $this->actingAs($user)->postJson(route('account.favorites.toggle', $service))->assertJson(['favorite' => true]);
        $this->assertTrue($user->favorites()->whereKey($service->id)->exists());

        $this->actingAs($user)->postJson(route('account.favorites.toggle', $service))->assertJson(['favorite' => false]);
        $this->assertFalse($user->favorites()->whereKey($service->id)->exists());
    }

    public function test_reorder_prefills_but_charges_nothing(): void
    {
        $user = $this->customer(10000);
        $service = $this->imeiService();
        $order = app(OrderService::class)->place($user, $service, [['imei' => '356938035643809'], ['imei' => '490154203237518']])->first();
        $balance = (float) $user->fresh()->balance;

        $this->actingAs($user)->get(route('account.orders.reorder', $order))
            ->assertRedirect(route('services.show', $service))
            ->assertSessionHasInput('fields.imei', "356938035643809\n490154203237518")
            ->assertSessionHas('reorder');

        $this->assertSame(2, Order::count());
        $this->assertSame(2, WalletTransaction::where('type', 'order')->count());
        $this->assertEquals($balance, (float) $user->fresh()->balance);
    }

    public function test_cannot_reorder_someone_elses_order(): void
    {
        $alice = $this->customer();
        $order = app(OrderService::class)->place($alice, $this->imeiService(), [['imei' => '356938035643809']])->first();

        $this->actingAs($this->customer())->get(route('account.orders.reorder', $order))->assertNotFound();
    }
}
