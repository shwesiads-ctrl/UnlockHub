<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\BulkUploadService;
use App\Services\OrderService;
use App\Support\Spreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkUploadTest extends TestCase
{
    use RefreshDatabase;

    private function file(array $rows, string $ext = 'xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk').'.'.$ext;
        file_put_contents($path, $ext === 'xlsx' ? Spreadsheet::xlsx($rows) : Spreadsheet::csv($rows));

        return new UploadedFile($path, 'orders.'.$ext, null, null, true);
    }

    public function test_preview_classifies_rows_and_creates_nothing(): void
    {
        $user = $this->customer(100000);
        $service = $this->imeiService();
        $bulk = app(BulkUploadService::class);

        $token = $bulk->upload($user, $service, $this->file([
            ['IMEI'], ['012345678901234'], ['356938035643809'], [''], ['012345678901234'],
        ]));
        $preview = $bulk->preview($user, $service, $bulk->get($user, $token, $service), app(OrderService::class));

        $this->assertSame('012345678901234', $preview['rows'][0]['input']['imei']); // leading zero kept
        $this->assertSame(2, $preview['valid']);
        $this->assertSame(1, $preview['duplicate']);
        $this->assertSame(0, Order::count());
    }

    public function test_confirm_orders_valid_rows_once(): void
    {
        $user = $this->customer(100000);
        $service = $this->imeiService();
        $bulk = app(BulkUploadService::class);
        $token = $bulk->upload($user, $service, $this->file([['IMEI'], ['356938035643809'], ['490154203237518']], 'csv'));

        $placed = $bulk->confirm($user, $service, $token, false, app(OrderService::class));
        $this->assertCount(2, $placed);
        $this->assertEquals(98000, (float) $user->fresh()->balance);

        // Same token again (double click after success): upload is gone, nothing new
        try {
            $bulk->confirm($user, $service, $token, false, app(OrderService::class));
        } catch (\RuntimeException) {
        }
        $this->assertSame(2, Order::count());
    }

    public function test_confirm_rechecks_balance(): void
    {
        $user = $this->customer(1500);
        $service = $this->imeiService();
        $bulk = app(BulkUploadService::class);
        $token = $bulk->upload($user, $service, $this->file([['IMEI'], ['356938035643809'], ['490154203237518']]));

        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        $bulk->confirm($user, $service, $token, false, app(OrderService::class));
    }
}
