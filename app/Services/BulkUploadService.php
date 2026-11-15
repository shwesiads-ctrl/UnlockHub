<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use App\Support\Spreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * CSV / XLSX bulk orders: upload → map columns → preview (valid / invalid / duplicate) → confirm.
 * Nothing is ordered or charged before confirm(); confirm re-checks price, balance and duplicates.
 */
class BulkUploadService
{
    public const TTL_MINUTES = 30;

    public static function maxRows(): int
    {
        return max(1, (int) setting('bulk.max_rows', 500));
    }

    public static function maxKb(): int
    {
        return max(50, (int) setting('bulk.max_kb', 2048));
    }

    /** Template: one column per service field. */
    public function template(Service $service, string $format): array
    {
        $service->loadMissing('fields');
        $header = $service->fields->pluck('label')->all() ?: ['IMEI'];
        $example = $service->fields->map(fn ($f) => match ($f->validation) {
            'imei' => '356938035643809',
            'email' => 'name@example.com',
            'serial' => 'F2LXXXXXXXX',
            default => $f->input_type === 'select' ? ($f->options[0] ?? '') : '',
        })->all() ?: ['356938035643809'];

        $name = 'bulk-'.Str::slug(Str::limit($service->name, 40, '')).'.'.$format;

        return $format === 'xlsx'
            ? [Spreadsheet::xlsx([$header, $example]), $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            : [Spreadsheet::csv([$header, $example]), $name, 'text/csv'];
    }

    /** Read the file and keep it for 30 minutes. Returns a token. */
    public function upload(User $user, Service $service, UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rows = Spreadsheet::read($file->getRealPath(), $ext === 'xlsx' ? 'xlsx' : 'csv', self::maxRows());

        $rows = array_values(array_filter($rows, fn ($r) => implode('', $r) !== ''));
        if (count($rows) < 2) {
            throw new RuntimeException('The file needs a header row and at least one order row.');
        }
        if (count($rows) - 1 > self::maxRows()) {
            throw new RuntimeException('Too many rows: the limit is '.self::maxRows().' orders per file.');
        }

        $header = array_map(fn ($h) => Str::limit(trim((string) $h), 60, ''), array_shift($rows));
        $token = Str::random(32);

        Cache::put($this->key($user, $token), [
            'service_id' => $service->id,
            'file' => Str::limit($file->getClientOriginalName(), 80, ''),
            'header' => $header,
            'rows' => array_map(fn ($r) => array_map(fn ($c) => Str::limit((string) $c, 255, ''), $r), $rows),
            'mapping' => $this->guessMapping($service, $header),
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function get(User $user, string $token, Service $service): array
    {
        $data = Cache::get($this->key($user, $token));

        if (! $data || $data['service_id'] !== $service->id) {
            throw new RuntimeException('This upload expired. Please upload the file again.');
        }

        return $data;
    }

    public function saveMapping(User $user, string $token, Service $service, array $mapping): array
    {
        $data = $this->get($user, $token, $service);
        $cols = count($data['header']);
        $data['mapping'] = collect($service->fields)->mapWithKeys(function ($f) use ($mapping, $cols) {
            $v = $mapping[$f->name] ?? '';

            return [$f->name => ($v !== '' && is_numeric($v) && (int) $v >= 0 && (int) $v < $cols) ? (int) $v : null];
        })->all();
        Cache::put($this->key($user, $token), $data, now()->addMinutes(self::TTL_MINUTES));

        return $data;
    }

    /**
     * Classify every row. Nothing is written.
     *
     * @return array{rows: list<array>, valid: int, invalid: int, duplicate: int, price: float, total: float, balance: float}
     */
    public function preview(User $user, Service $service, array $data, OrderService $orders): array
    {
        $service->loadMissing('fields');
        $seen = [];
        $out = [];

        foreach ($data['rows'] as $i => $cells) {
            $input = [];
            foreach ($service->fields as $f) {
                $col = $data['mapping'][$f->name] ?? null;
                $input[$f->name] = $col === null ? '' : trim((string) ($cells[$col] ?? ''));
            }

            $rules = $attrs = [];
            foreach ($service->fields as $f) {
                $rules[$f->name] = $f->rules();
                $attrs[$f->name] = $f->label;
            }
            $v = Validator::make($input, $rules, [], $attrs);

            $row = ['line' => $i + 2, 'input' => $input, 'status' => 'valid', 'reason' => null];
            $ident = $this->identifier($service, $input);

            if ($v->fails()) {
                $row['status'] = 'invalid';
                $row['reason'] = implode(' ', $v->errors()->all());
            } elseif ($ident !== '' && isset($seen[$ident])) {
                $row['status'] = 'duplicate';
                $row['reason'] = 'Same as line '.$seen[$ident].' in this file.';
            }
            if ($ident !== '' && ! isset($seen[$ident])) {
                $seen[$ident] = $i + 2;
            }
            $out[] = $row;
        }

        // Already in an open order?
        $validRows = array_filter($out, fn ($r) => $r['status'] === 'valid');
        $open = $orders->openDuplicates($user, $service, array_column($validRows, 'input'))->keyBy('imei');
        foreach ($out as &$row) {
            if ($row['status'] === 'valid' && ($o = $open->get($this->identifier($service, $row['input'])))) {
                $row['status'] = 'duplicate';
                $row['open_order'] = true;
                $row['reason'] = "Already in open order #{$o->order_no} ({$o->statusLabel()}).";
            }
        }
        unset($row);

        $counts = array_count_values(array_column($out, 'status'));
        $price = (float) $service->priceFor($user);

        return [
            'rows' => $out,
            'valid' => $counts['valid'] ?? 0,
            'invalid' => $counts['invalid'] ?? 0,
            'duplicate' => $counts['duplicate'] ?? 0,
            'open_duplicates' => count(array_filter($out, fn ($r) => ! empty($r['open_order']))),
            'price' => $price,
            'price_currency' => $service->price_currency ?? 'ks',
            'total' => round($price * ($counts['valid'] ?? 0), 2),
            'balance' => ($service->price_currency ?? 'ks') === 'points' ? (int) $user->fresh()->points : (float) $user->fresh()->balance,
        ];
    }

    /** Rows with problems, for the customer to fix. */
    public function errorFile(Service $service, array $data, array $preview): string
    {
        $header = array_merge(['Line', 'Problem'], $data['header']);
        $rows = [$header];
        foreach ($preview['rows'] as $r) {
            if ($r['status'] !== 'valid') {
                $rows[] = array_merge([$r['line'], ucfirst($r['status']).': '.$r['reason']], $data['rows'][$r['line'] - 2] ?? []);
            }
        }

        return Spreadsheet::csv($rows);
    }

    /** Place the valid rows (and, if asked and allowed, open-order duplicates). Re-checks everything. */
    public function confirm(User $user, Service $service, string $token, bool $includeOpenDuplicates, OrderService $orders): \Illuminate\Support\Collection
    {
        $data = $this->get($user, $token, $service);
        $preview = $this->preview($user, $service->fresh(), $data, $orders);

        $allowDupes = $includeOpenDuplicates && ($service->duplicate_policy ?? 'warn') !== 'block';
        $rows = [];
        foreach ($preview['rows'] as $r) {
            if ($r['status'] === 'valid' || ($allowDupes && ! empty($r['open_order']))) {
                $rows[] = array_filter($r['input'], fn ($v) => $v !== '');
            }
        }

        if (! $rows) {
            throw new RuntimeException('There are no valid rows to order.');
        }

        $placed = $orders->place($user, $service->fresh(), $rows, 'Bulk file: '.$data['file'], [
            'key' => 'bulk:'.$token,          // pressing confirm twice never orders twice
            'source' => 'web',
            'allow_duplicates' => $allowDupes,
        ]);

        Cache::forget($this->key($user, $token));

        return $placed;
    }

    private function guessMapping(Service $service, array $header): array
    {
        $service->loadMissing('fields');
        $norm = fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
        $map = [];
        foreach ($service->fields as $f) {
            $map[$f->name] = null;
            foreach ($header as $i => $h) {
                if (in_array($norm($h), [$norm($f->name), $norm($f->label)], true)
                    || ($f->validation === 'imei' && str_contains($norm($h), 'imei'))
                    || ($f->validation === 'serial' && in_array($norm($h), ['sn', 'serial', 'serialnumber'], true))) {
                    $map[$f->name] = $i;
                    break;
                }
            }
        }
        // Single-field service and nothing matched: first column
        if ($service->fields->count() === 1 && $map[$service->fields->first()->name] === null) {
            $map[$service->fields->first()->name] = 0;
        }

        return $map;
    }

    private function identifier(Service $service, array $input): string
    {
        foreach (['imei', 'sn', 'serial', 'ecid', 'username', 'email'] as $k) {
            if (($input[$k] ?? '') !== '') {
                return (string) $input[$k];
            }
        }

        return (string) (collect($input)->first(fn ($v) => $v !== '') ?? '');
    }

    private function key(User $user, string $token): string
    {
        return 'bulk-upload:'.$user->id.':'.$token;
    }
}
