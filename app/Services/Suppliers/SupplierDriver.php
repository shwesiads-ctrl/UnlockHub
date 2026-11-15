<?php

namespace App\Services\Suppliers;

use App\Models\Order;
use App\Models\Supplier;

/** A supplier API connection. */
interface SupplierDriver
{
    /**
     * Balance and account details.
     *
     * @return array{credit: float, currency: ?string, email: ?string}
     *
     * @throws SupplierException
     */
    public function accountInfo(Supplier $supplier): array;

    /**
     * Every service the supplier offers to us.
     *
     * @return array<int, array{id: string, name: string, group: string, credit: float, time: ?string, type: string, info: ?string, requires: array<int, string>}>
     *
     * @throws SupplierException
     */
    public function serviceList(Supplier $supplier): array;

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
     *
     * @throws SupplierException
     */
    public function checkOrder(Supplier $supplier, Order $order): array;
}
