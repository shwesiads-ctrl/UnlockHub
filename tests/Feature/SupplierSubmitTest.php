<?php

namespace Tests\Feature;

use App\Jobs\SubmitOrderToSupplier;
use App\Models\Order;
use App\Models\Supplier;
use App\Services\OrderService;
use App\Services\Suppliers\SupplierDriver;
use App\Services\Suppliers\SupplierException;
use App\Services\Suppliers\SupplierManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SupplierSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function apiOrder(): Order
    {
        Queue::fake(); // place() dispatches; the test runs the job by hand

        $supplier = Supplier::create(['name' => 'S', 'api_url' => 'https://example.test', 'api_username' => 'u', 'api_key' => 'k', 'status' => 'active']);
        $service = $this->imeiService(['processing_mode' => 'api', 'supplier_id' => $supplier->id, 'supplier_service_id' => '10', 'fallback_to_manual' => true]);

        return app(OrderService::class)->place($this->customer(), $service, [['imei' => '356938035643809']])->first();
    }

    private function fakeDriver(callable $placeOrder): void
    {
        $driver = Mockery::mock(SupplierDriver::class);
        $driver->shouldReceive('placeOrder')->andReturnUsing($placeOrder);
        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('driverFor')->andReturn($driver);
        $this->app->instance(SupplierManager::class, $manager);
    }

    public function test_order_is_sent_to_the_supplier_only_once(): void
    {
        $order = $this->apiOrder();
        $calls = 0;
        $this->fakeDriver(function () use (&$calls) { $calls++; return 'REF-1'; });

        app()->call([new SubmitOrderToSupplier($order->id), 'handle']);
        app()->call([new SubmitOrderToSupplier($order->id), 'handle']); // duplicate job

        $this->assertSame(1, $calls);
        $this->assertSame('REF-1', $order->fresh()->supplier_order_id);
    }

    public function test_read_timeout_is_not_retried_automatically(): void
    {
        $order = $this->apiOrder();
        $this->fakeDriver(fn () => throw new SupplierException('No reply from supplier (timeout).', retryable: false));

        app()->call([new SubmitOrderToSupplier($order->id), 'handle']);

        $order->refresh();
        $this->assertTrue($order->api_failed);
        $this->assertSame('manual', $order->processing_mode);
        Queue::assertPushed(SubmitOrderToSupplier::class, 1); // only the original dispatch from place()
    }

    public function test_connection_failure_is_retried(): void
    {
        $order = $this->apiOrder();
        $this->fakeDriver(fn () => throw new SupplierException('Could not connect', retryable: true));

        app()->call([new SubmitOrderToSupplier($order->id), 'handle']);

        $this->assertFalse($order->fresh()->api_failed);
        Queue::assertPushed(SubmitOrderToSupplier::class, 2);
    }
}
