<?php

namespace Tests;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function customer(float $balance = 100000): User
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => $balance])->save();

        return $user->fresh();
    }

    /** Active manual IMEI service with one IMEI field, 1,000 Ks. */
    protected function imeiService(array $attributes = []): Service
    {
        $category = ServiceCategory::create(['type' => 'imei', 'name' => 'Test', 'slug' => 'test-'.Str::random(6)]);

        $service = Service::create($attributes + [
            'service_category_id' => $category->id,
            'type' => 'imei',
            'name' => 'Test unlock',
            'slug' => 'test-unlock-'.Str::random(6),
            'cost_price' => 700,
            'default_price' => 1000,
            'status' => 'active',
            'processing_mode' => 'manual',
            'allow_bulk' => true,
            'max_bulk' => 100,
            'duplicate_policy' => 'warn',
        ]);

        $service->fields()->create([
            'label' => 'IMEI', 'name' => 'imei', 'input_type' => 'text', 'validation' => 'none',
            'is_required' => true, 'is_bulk_field' => true, 'sort' => 0,
        ]);

        return $service->fresh();
    }
}
