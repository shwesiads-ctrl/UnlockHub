<?php

namespace App\Services\Suppliers;

use RuntimeException;

class SupplierException extends RuntimeException
{
    /**
     * @param  bool  $retryable  true only when the request surely never reached the supplier
     *                           (DNS / connection refused / connect timeout). A read timeout is
     *                           NOT retryable: the supplier may already have created the order.
     */
    public function __construct(string $message = '', public bool $retryable = false)
    {
        parent::__construct($message);
    }
}
