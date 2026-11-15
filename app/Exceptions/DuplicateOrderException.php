<?php

namespace App\Exceptions;

use Illuminate\Support\Collection;
use RuntimeException;

/** The user already has an open order for the same service + IMEI/SN. */
class DuplicateOrderException extends RuntimeException
{
    /** @param Collection<int, \App\Models\Order> $existing */
    public function __construct(public Collection $existing, public bool $blocked)
    {
        $first = $existing->first();
        parent::__construct(
            ($blocked ? 'Duplicate order not allowed: ' : 'Possible duplicate: ')
            ."{$first->imei} is already in order #{$first->order_no} ({$first->statusLabel()})"
            .($existing->count() > 1 ? ' and '.($existing->count() - 1).' more' : '').'.'
        );
    }
}
