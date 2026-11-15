<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Statement {{ $number }}</title>
<style>
    @page { margin: 28mm 14mm 20mm 14mm; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { color: #0F172A; font-size: 10px; line-height: 1.45; }
    header { position: fixed; top: -20mm; left: 0; right: 0; height: 16mm; border-bottom: 2px solid #2459E8; }
    header table { width: 100%; }
    .brand { font-size: 16px; font-weight: bold; color: #0F172A; }
    .muted { color: #556070; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .meta { width: 100%; margin: 6px 0 14px; }
    .meta td { vertical-align: top; padding: 0; }
    .box { border: 1px solid #E2E8F0; border-radius: 6px; padding: 8px 10px; }
    .summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
    .summary td { width: 25%; border: 1px solid #E2E8F0; border-radius: 6px; padding: 8px 10px; }
    .summary .label { font-size: 8.5px; color: #556070; text-transform: uppercase; letter-spacing: .4px; }
    .summary .value { font-size: 13px; font-weight: bold; margin-top: 2px; }
    .ok { color: #15803D; } .bad { color: #B91C1C; }
    table.rows { width: 100%; border-collapse: collapse; }
    table.rows th { background: #EEF2F7; color: #556070; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; text-align: left; padding: 6px 7px; }
    table.rows td { padding: 6px 7px; border-bottom: 1px solid #E2E8F0; vertical-align: top; }
    table.rows tr:nth-child(even) td { background: #FAFBFD; }
    .r { text-align: right; white-space: nowrap; }
    .nowrap { white-space: nowrap; }
    .opening td { background: #F4F6FA !important; font-weight: bold; }
    .types { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .types td { padding: 4px 0; border-bottom: 1px dotted #E2E8F0; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #8A93A3; }
</style>
</head>
<body>
<header>
    <table><tr>
        <td>
            @if ($logo)
                <img src="{{ $logo }}" alt="" style="height: 30px">
            @else
                <span class="brand">{{ setting('site.name', config('app.name')) }}</span>
            @endif
        </td>
        <td style="text-align: right" class="muted">
            {{ setting('site.name', config('app.name')) }}<br>
            {{ parse_url(url('/'), PHP_URL_HOST) }}@if (setting('contact.email')) · {{ setting('contact.email') }}@endif
        </td>
    </tr></table>
</header>

<footer>
    {{ $number }} · Generated {{ $generatedAt->format('d M Y H:i') }} ({{ config('app.timezone') }}). Amounts in Myanmar Kyat (Ks). This statement is produced automatically from your wallet records.
</footer>

<h1>Wallet statement</h1>
<table class="meta"><tr>
    <td style="width: 55%">
        <div class="box">
            <strong>{{ $user->name }}</strong> ({{ $user->username }})<br>
            @if ($user->shop_name) {{ $user->shop_name }}<br> @endif
            @if ($user->email) {{ $user->email }}<br> @endif
            @if ($user->phone) {{ $user->phone }} @endif
        </div>
    </td>
    <td style="width: 45%; padding-left: 10px">
        <div class="box">
            <span class="muted">Period</span><br>
            <strong>{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</strong><br>
            <span class="muted">Statement no.</span> {{ $number }}
            @if ($type)<br><span class="muted">Showing only:</span> {{ $types[$type] ?? $type }}@endif
        </div>
    </td>
</tr></table>

<table class="summary"><tr>
    <td><div class="label">Opening balance</div><div class="value">{{ number_format($opening, 0) }} Ks</div></td>
    <td><div class="label">Money in</div><div class="value ok">+{{ number_format($credits, 0) }} Ks</div></td>
    <td><div class="label">Money out</div><div class="value bad">−{{ number_format($debits, 0) }} Ks</div></td>
    <td><div class="label">Closing balance</div><div class="value">{{ number_format($closing, 0) }} Ks</div></td>
</tr></table>

<table class="rows">
    <thead>
        <tr><th style="width: 16%">Date</th><th style="width: 14%">Type</th><th>Description</th><th class="r" style="width: 14%">Amount</th><th class="r" style="width: 14%">Balance</th></tr>
    </thead>
    <tbody>
        @unless ($type)
            <tr class="opening"><td class="nowrap">{{ $from->format('d M Y') }}</td><td colspan="3">Opening balance</td><td class="r">{{ number_format($opening, 0) }}</td></tr>
        @endunless
        @forelse ($rows as $t)
            <tr>
                <td class="nowrap">{{ $t->created_at->format('d M Y H:i') }}</td>
                <td>{{ $types[$t->type] ?? $t->type }}</td>
                <td>{{ $t->description }}</td>
                <td class="r {{ $t->amount > 0 ? 'ok' : 'bad' }}">{{ $t->amount > 0 ? '+' : '−' }}{{ number_format(abs($t->amount), 0) }}</td>
                <td class="r">{{ number_format($t->balance_after, 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted" style="text-align: center; padding: 18px">No transactions in this period.</td></tr>
        @endforelse
    </tbody>
</table>

@if ($truncated)
    <p class="muted">Only the first {{ number_format(\App\Services\StatementService::PDF_MAX_ROWS) }} transactions are listed. Choose a shorter period to see the rest.</p>
@endif

@if ($byType->isNotEmpty())
    <table class="types">
        <tr><td colspan="3"><strong>Summary by type</strong></td></tr>
        @foreach ($byType as $k => $v)
            <tr>
                <td>{{ $types[$k] ?? $k }}</td>
                <td class="muted">{{ $v['n'] }} {{ $v['n'] === 1 ? 'transaction' : 'transactions' }}</td>
                <td class="r {{ $v['total'] >= 0 ? 'ok' : 'bad' }}">{{ $v['total'] >= 0 ? '+' : '−' }}{{ number_format(abs($v['total']), 0) }} Ks</td>
            </tr>
        @endforeach
    </table>
@endif
</body>
</html>
