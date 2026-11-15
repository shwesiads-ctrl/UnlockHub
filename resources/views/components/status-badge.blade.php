@props(['status'])
@php
    $labels = [
        'pending' => 'Pending', 'in_process' => 'In Process', 'success' => 'Success',
        'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'approved' => 'Approved',
        'manual' => 'Manual', 'api' => 'API',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$status]) }}>{{ $labels[$status] ?? ucfirst($status) }}</span>
