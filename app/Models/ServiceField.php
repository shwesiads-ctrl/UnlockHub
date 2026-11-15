<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceField extends Model
{
    protected $fillable = [
        'service_id', 'label', 'name', 'input_type', 'options', 'validation',
        'regex', 'placeholder', 'is_required', 'is_bulk_field', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_bulk_field' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Laravel validation rules for one value of this field. */
    public function rules(): array
    {
        $rules = [$this->is_required ? 'required' : 'nullable', 'string', 'max:255'];

        match ($this->validation) {
            'imei' => $rules[] = new \App\Rules\Imei,
            'serial' => $rules[] = 'regex:/^[A-Za-z0-9\-]{5,30}$/',
            'email' => $rules[] = 'email',
            'regex' => $this->regex ? $rules[] = 'regex:'.$this->regex : null,
            default => null,
        };

        if ($this->input_type === 'select' && $this->options) {
            $rules[] = 'in:'.implode(',', $this->options);
        }

        return $rules;
    }
}
