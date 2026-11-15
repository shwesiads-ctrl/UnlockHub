<svg xmlns="http://www.w3.org/2000/svg" width="760" height="{{ $height }}" viewBox="0 0 760 {{ $height }}" role="img" aria-label="{{ __('Wallet transaction receipt') }}">
    <rect width="760" height="{{ $height }}" fill="#eef2f7"/>
    <rect x="24" y="24" width="712" height="{{ $height - 48 }}" rx="22" fill="#ffffff"/>
    <path d="M46 24H714Q736 24 736 46V158H24V46Q24 24 46 24" fill="#315efb"/>
    <g font-family="Arial, sans-serif" fill="#172033">
        <text x="58" y="82" font-size="28" font-weight="700" fill="#ffffff">{{ mb_strimwidth($site, 0, 38, '…') }}</text>
        <text x="58" y="122" font-size="17" fill="#ffffff">{{ __('WALLET TRANSACTION RECEIPT') }}</text>
        @foreach ($rows as $label => $value)
            <text x="58" y="{{ 205 + $loop->index * 43 }}" font-size="16" fill="#64748b">{{ $label }}</text>
            <text x="702" y="{{ 205 + $loop->index * 43 }}" text-anchor="end" font-size="18" font-weight="600">{{ $value }}</text>
        @endforeach
        <line x1="58" y1="498" x2="702" y2="498" stroke="#e2e8f0"/>
        <text x="58" y="531" font-size="15" fill="#64748b">{{ __('DESCRIPTION') }}</text>
        @foreach ($description as $line)
            <text x="58" y="{{ 562 + $loop->index * 26 }}" font-size="17">{{ $line }}</text>
        @endforeach
        <text x="380" y="{{ $height - 55 }}" text-anchor="middle" font-size="13" fill="#64748b">{{ __('System-generated wallet record · Keep for your reference') }}</text>
    </g>
</svg>
