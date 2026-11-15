<?php

namespace App\Services\Suppliers;

use App\Models\Order;
use App\Models\Supplier;

/** A supplier API connection (DHRU Fusion driver is added in step 6). */
interface SupplierDriver
{
    /**
     * Send the order. Returns the supplier's order reference.
     *
     * @throws SupplierException when the supplier refuses or cannot be reached
     */
    public function placeOrder(Supplier $supplier, Order $order): string;

    /**
     * Ask for the result of an order already placed.
     *
     * @return array{status: 'pending'|'in_process'|'success'|'rejected', result: ?string}
     */
    public function checkOrder(Supplier $supplier, Order $order): array;
}
