<x-mail::message>
# Your wallet statement

Hello {{ $user->name }},

Your statement for **{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}** is attached as a PDF.

<x-mail::table>
| | |
|:--|--:|
| Opening balance | {{ number_format($opening, 0) }} Ks |
| Money in | +{{ number_format($credits, 0) }} Ks |
| Money out | −{{ number_format($debits, 0) }} Ks |
| **Closing balance** | **{{ number_format($closing, 0) }} Ks** |
</x-mail::table>

<x-mail::button :url="route('account.statement')">
View statement online
</x-mail::button>

If something looks wrong, reply to this email or contact support.

— {{ setting('site.name', config('app.name')) }}
</x-mail::message>
