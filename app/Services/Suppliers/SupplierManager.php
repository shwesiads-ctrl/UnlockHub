<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;

/** Picks the driver for a supplier's `driver` column. */
class SupplierManager
{
    /** @var array<string, array{class: class-string<SupplierDriver>, label: string}> */
    public const DRIVERS = [
        'dhru' => ['class' => DhruFusionDriver::class, 'label' => 'DHRU Fusion'],
    ];

    public function driverFor(Supplier $supplier): SupplierDriver
    {
        $class = self::DRIVERS[$supplier->driver]['class'] ?? null;

        if (! $class) {
            throw new SupplierException("No API driver installed for \"{$supplier->driver}\".");
        }

        return app($class);
    }

    /** Fetch balance and save it on the supplier (also clears / records errors). */
    public function syncBalance(Supplier $supplier): array
    {
        try {
            $info = $this->driverFor($supplier)->accountInfo($supplier);
        } catch (SupplierException $e) {
            $supplier->forceFill(['status' => 'error', 'last_error' => $e->getMessage(), 'last_synced_at' => now()])->save();
            throw $e;
        }

        $supplier->forceFill([
            'balance' => $info['credit'],
            'currency' => $info['currency'] ? strtoupper(substr($info['currency'], 0, 3)) : $supplier->currency,
            'status' => $supplier->status === 'error' ? 'active' : $supplier->status,
            'last_error' => null,
            'last_synced_at' => now(),
        ])->save();

        return $info;
    }
}
