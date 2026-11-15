<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;

/** Picks the driver for a supplier's `driver` column. */
class SupplierManager
{
    /** @var array<string, class-string<SupplierDriver>> */
    protected array $drivers = [
        // 'dhru' => DhruFusionDriver::class,  ← step 6
    ];

    public function driverFor(Supplier $supplier): SupplierDriver
    {
        $class = $this->drivers[$supplier->driver] ?? null;

        if (! $class) {
            throw new SupplierException("No API driver installed for \"{$supplier->driver}\".");
        }

        return app($class);
    }
}
