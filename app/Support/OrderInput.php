<?php

namespace App\Support;

use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Turns the submitted service fields into one input array per order.
 * A bulk field (textarea, one value per line) creates one order per line.
 */
class OrderInput
{
    /** @return array<int, array<string, string>> */
    public static function rows(Service $service, array $submitted): array
    {
        $service->loadMissing('fields');
        $fields = $service->fields;

        $bulkField = $service->allow_bulk ? $fields->firstWhere('is_bulk_field', true) : null;
        $common = [];
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            if ($bulkField && $field->is($bulkField)) {
                continue;
            }
            $common[$field->name] = trim((string) ($submitted[$field->name] ?? ''));
            $rules['fields.'.$field->name] = $field->rules();
            $attributes['fields.'.$field->name] = $field->label;
        }

        // Error keys match the form inputs: fields[imei] → fields.imei
        Validator::make(['fields' => $common], $rules, [], $attributes)->validate();

        if (! $bulkField) {
            return [array_filter($common, fn ($v) => $v !== '')];
        }

        // Bulk: split lines, trim, drop blanks and duplicates
        $lines = collect(preg_split('/\r\n|\r|\n/', (string) ($submitted[$bulkField->name] ?? '')))
            ->map(fn ($l) => trim($l))->filter(fn ($l) => $l !== '')->unique()->values();

        $key = 'fields.'.$bulkField->name;

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([$key => "Enter at least one {$bulkField->label}."]);
        }

        if ($lines->count() > $service->max_bulk) {
            throw ValidationException::withMessages([$key => "Maximum {$service->max_bulk} per order — you entered {$lines->count()}."]);
        }

        $bad = [];
        foreach ($lines as $i => $line) {
            $v = Validator::make(['v' => $line], ['v' => $bulkField->rules()], [], ['v' => $bulkField->label]);
            if ($v->fails()) {
                $bad[] = 'Line '.($i + 1).' ('.$line.'): '.$v->errors()->first('v');
            }
        }

        if ($bad) {
            throw ValidationException::withMessages([$key => implode("\n", array_slice($bad, 0, 10)).(count($bad) > 10 ? "\n…and ".(count($bad) - 10).' more' : '')]);
        }

        return $lines->map(fn ($line) => array_filter([$bulkField->name => $line] + $common, fn ($v) => $v !== ''))->all();
    }
}
