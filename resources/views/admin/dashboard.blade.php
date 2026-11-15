@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
@php
    $today = \App\Models\Order::whereDate('created_at', today());
    $sales = (clone $today)->whereNotIn('status', ['rejected', 'cancelled'])->sum('price');
    $profit = (clone $today)->where('status', 'success')->selectRaw('COALESCE(SUM(price - cost),0) as p')->value('p');
    $ordersToday = (clone $today)->count();
    $apiToday = (clone $today)->where('processing_mode', 'api')->count();
@endphp
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Today's sales</span><span class="font-display text-[28px] font-bold">{{ money($sales) }}</span><span class="text-[13px] text-ok font-semibold">Profit {{ money($profit) }}</span></div>
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Orders today</span><span class="font-display text-[28px] font-bold">{{ $ordersToday }}</span><span class="text-[13px] text-muted">{{ $apiToday }} API · {{ $ordersToday - $apiToday }} Manual</span></div>
    <a href="#" class="rounded-2xl p-5 flex flex-col gap-2 no-underline bg-warn-soft border border-warn text-warn hover:text-warn"><span class="text-[13px] font-semibold">Payments to approve</span><span class="font-display text-[28px] font-bold">{{ $adminBadges['payments'] ?? 0 }}</span><span class="text-[13px] font-semibold">Review receipts →</span></a>
    <a href="#" class="rounded-2xl p-5 flex flex-col gap-2 no-underline bg-danger-soft border border-danger text-danger hover:text-danger"><span class="text-[13px] font-semibold">Manual queue</span><span class="font-display text-[28px] font-bold">{{ $adminBadges['manual'] ?? 0 }}</span><span class="text-[13px] font-semibold">Process orders →</span></a>
</div>
@endsection
