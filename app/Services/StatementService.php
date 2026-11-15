<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Wallet statements for a date range: on screen, as PDF, and by email. */
class StatementService
{
    public const TYPES = [
        'deposit' => 'Top-up',
        'order' => 'Order',
        'refund' => 'Refund',
        'points_redeem' => 'Points redeemed',
        'points_purchase' => 'Wallet converted to points',
        'admin_credit' => 'Admin credit',
        'admin_debit' => 'Admin debit',
        'transfer_in' => 'Transfer in',
        'transfer_out' => 'Transfer out',
        'reseller_profit' => 'Reseller profit',
        'commission' => 'Commission',
        'withdrawal' => 'Withdrawal',
        'withdrawal_refund' => 'Withdrawal returned',
    ];

    /** Max rows in one PDF (keeps memory sane on very busy accounts). */
    public const PDF_MAX_ROWS = 3000;

    /** @return array{0: Carbon, 1: Carbon} from/to, defaults to this month */
    public static function range(?string $from, ?string $to): array
    {
        try {
            $f = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
            $t = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            [$f, $t] = [now()->startOfMonth(), now()->endOfDay()];
        }

        if ($f->gt($t)) {
            [$f, $t] = [$t->copy()->startOfDay(), $f->copy()->endOfDay()];
        }

        return [$f, $t];
    }

    /** Everything a statement needs: opening/closing balance, totals and rows (oldest first). */
    public function build(User $user, Carbon $from, Carbon $to, ?string $type = null): array
    {
        $opening = (float) (WalletTransaction::where('user_id', $user->id)
            ->where('created_at', '<', $from)->latest('id')->value('balance_after') ?? 0);

        $base = WalletTransaction::where('user_id', $user->id)->whereBetween('created_at', [$from, $to]);

        $closing = (float) ((clone $base)->latest('id')->value('balance_after') ?? $opening);
        $credits = (float) (clone $base)->where('amount', '>', 0)->sum('amount');
        $debits = (float) abs((clone $base)->where('amount', '<', 0)->sum('amount'));

        $byType = (clone $base)->selectRaw('type, COUNT(*) AS n, SUM(amount) AS total')->groupBy('type')->get()
            ->mapWithKeys(fn ($r) => [$r->type => ['n' => (int) $r->n, 'total' => (float) $r->total]]);

        $rows = (clone $base)->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('id')->limit(self::PDF_MAX_ROWS + 1)->get();

        return [
            'user' => $user,
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'opening' => $opening,
            'closing' => $closing,
            'credits' => $credits,
            'debits' => $debits,
            'byType' => $byType,
            'rows' => $rows->take(self::PDF_MAX_ROWS),
            'truncated' => $rows->count() > self::PDF_MAX_ROWS,
            'types' => self::TYPES,
            'generatedAt' => now(),
            'number' => 'ST-'.$user->id.'-'.$from->format('Ymd').'-'.$to->format('Ymd'),
        ];
    }

    /** Render the statement as a PDF (binary string). */
    public function pdf(array $data): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);   // never fetch URLs while rendering
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.statement', $data + ['logo' => $this->logoDataUri()])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Page numbers in the footer
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(500, 815, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.45, 0.48, 0.53]);

        return $dompdf->output();
    }

    /** Build, render and email the statement. Returns false when the user has no email. */
    public function email(User $user, Carbon $from, Carbon $to, ?string $type = null): bool
    {
        if (! $user->email) {
            return false;
        }

        $data = $this->build($user, $from, $to, $type);

        \Illuminate\Support\Facades\Mail::to($user->email, $user->name)->send(new \App\Mail\StatementMail(
            $user, $from, $to, $data['opening'], $data['closing'], $data['credits'], $data['debits'],
            $this->pdf($data), $this->filename($data),
        ));

        return true;
    }

    public function filename(array $data): string
    {
        return 'statement-'.$data['user']->username.'-'.$data['from']->format('Ymd').'-'.$data['to']->format('Ymd').'.pdf';
    }

    /** Site logo embedded as data: URI (dompdf runs with remote access off). */
    private function logoDataUri(): ?string
    {
        $path = setting('site.logo');
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null; // dompdf can't draw WebP reliably
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }
}
