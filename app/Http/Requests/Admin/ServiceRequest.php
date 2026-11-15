<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $bools = ['is_hot', 'allow_bulk', 'allow_cancel_pending', 'fallback_to_manual'];
        $merge = [];
        foreach ($bools as $b) {
            $merge[$b] = $this->boolean($b);
        }

        // Normalise field rows (drop empty ones, cast checkboxes)
        $fields = collect($this->input('fields', []))
            ->filter(fn ($f) => filled($f['label'] ?? null) || filled($f['name'] ?? null))
            ->map(fn ($f) => [
                'label' => trim((string) ($f['label'] ?? '')),
                'name' => strtolower(trim((string) ($f['name'] ?? ''))),
                'input_type' => $f['input_type'] ?? 'text',
                'validation' => $f['validation'] ?? 'none',
                'options' => $f['options'] ?? null,
                'regex' => $f['regex'] ?? null,
                'placeholder' => $f['placeholder'] ?? null,
                'is_required' => ! empty($f['is_required']),
                'is_bulk_field' => ! empty($f['is_bulk_field']),
            ])->values()->all();

        $merge['fields'] = $fields;
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'delivery_time' => ['nullable', 'string', 'max:60'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'default_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_hot' => ['boolean'],
            'allow_bulk' => ['boolean'],
            'max_bulk' => ['required', 'integer', 'min:1', 'max:500'],
            'allow_cancel_pending' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'hidden', 'disabled'])],
            'processing_mode' => ['required', Rule::in(['manual', 'api'])],
            'supplier_id' => ['nullable', 'required_if:processing_mode,api', 'exists:suppliers,id'],
            'supplier_service_id' => ['nullable', 'required_if:processing_mode,api', 'string', 'max:100'],
            'fallback_to_manual' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'popup' => ['nullable', 'string', 'max:200000'],

            'prices' => ['array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            'fields' => ['array', 'max:10'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.name' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.input_type' => ['required', Rule::in(['text', 'number', 'textarea', 'select', 'email'])],
            'fields.*.validation' => ['required', Rule::in(['none', 'imei', 'serial', 'email', 'regex'])],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.regex' => ['nullable', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:100'],
            'fields.*.is_required' => ['boolean'],
            'fields.*.is_bulk_field' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.*.name.regex' => 'Field keys use lowercase letters, numbers and _ (e.g. imei, serial_no).',
            'fields.*.name.distinct' => 'Each field key must be different.',
            'supplier_id.required_if' => 'Choose a supplier for API mode.',
            'supplier_service_id.required_if' => 'Choose the supplier service for API mode.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($this->input('status') === 'active' && (float) $this->input('default_price') <= 0) {
                    $v->errors()->add('default_price', 'Set a price above 0 before making the service Active.');
                }

                if ($this->boolean('allow_bulk') && ! collect($this->input('fields'))->contains('is_bulk_field', true)) {
                    $v->errors()->add('allow_bulk', 'Bulk orders need one field marked "Bulk (one per line)".');
                }

                foreach ($this->input('fields', []) as $i => $f) {
                    if ($f['validation'] === 'regex' && filled($f['regex']) && @preg_match($f['regex'], '') === false) {
                        $v->errors()->add("fields.$i.regex", 'This regex is not valid. Example: /^[A-Z0-9]{8}$/');
                    }
                    if ($f['input_type'] === 'select' && blank($f['options'])) {
                        $v->errors()->add("fields.$i.options", 'Dropdown fields need options (comma separated).');
                    }
                }
            },
        ];
    }
}
