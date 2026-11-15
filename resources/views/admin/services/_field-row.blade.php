<div class="rounded-xl bg-surface-2 p-3.5 flex flex-col gap-3" data-row>
    <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(150px,1fr))]">
        <div>
            <label class="label text-xs" for="fl_{{ $i }}">Label</label>
            <input id="fl_{{ $i }}" name="fields[{{ $i }}][label]" value="{{ $f['label'] }}" class="input h-10" placeholder="IMEI">
            @if (is_int($i)) <x-field-error :name="'fields.'.$i.'.label'" /> @endif
        </div>
        <div>
            <label class="label text-xs" for="fn_{{ $i }}">Key</label>
            <input id="fn_{{ $i }}" name="fields[{{ $i }}][name]" value="{{ $f['name'] }}" class="input h-10 font-mono" placeholder="imei">
            @if (is_int($i)) <x-field-error :name="'fields.'.$i.'.name'" /> @endif
        </div>
        <div>
            <label class="label text-xs" for="ft_{{ $i }}">Input</label>
            <select id="ft_{{ $i }}" name="fields[{{ $i }}][input_type]" class="input h-10">
                @foreach (['text' => 'Text', 'number' => 'Number', 'textarea' => 'Long text', 'select' => 'Dropdown', 'email' => 'Email'] as $k => $l)
                    <option value="{{ $k }}" @selected($f['input_type'] === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label text-xs" for="fv_{{ $i }}">Check</label>
            <select id="fv_{{ $i }}" name="fields[{{ $i }}][validation]" class="input h-10">
                @foreach (['none' => 'None', 'imei' => 'IMEI (Luhn)', 'serial' => 'Serial', 'email' => 'Email', 'regex' => 'Regex'] as $k => $l)
                    <option value="{{ $k }}" @selected($f['validation'] === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(150px,1fr))]">
        <div>
            <label class="label text-xs" for="fp_{{ $i }}">Placeholder</label>
            <input id="fp_{{ $i }}" name="fields[{{ $i }}][placeholder]" value="{{ $f['placeholder'] }}" class="input h-10">
        </div>
        <div>
            <label class="label text-xs" for="fo_{{ $i }}">Dropdown options <span class="font-normal text-muted">(comma separated)</span></label>
            <input id="fo_{{ $i }}" name="fields[{{ $i }}][options]" value="{{ $f['options'] }}" class="input h-10" placeholder="Galaxy S23, Galaxy A54">
            @if (is_int($i)) <x-field-error :name="'fields.'.$i.'.options'" /> @endif
        </div>
        <div>
            <label class="label text-xs" for="fr_{{ $i }}">Regex <span class="font-normal text-muted">(if Check = Regex)</span></label>
            <input id="fr_{{ $i }}" name="fields[{{ $i }}][regex]" value="{{ $f['regex'] }}" class="input h-10 font-mono" placeholder="/^[A-Z0-9]{8}$/">
            @if (is_int($i)) <x-field-error :name="'fields.'.$i.'.regex'" /> @endif
        </div>
    </div>
    <div class="flex flex-wrap gap-4 items-center text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="fields[{{ $i }}][is_required]" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($f['is_required'])>Required</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="fields[{{ $i }}][is_bulk_field]" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($f['is_bulk_field'])>Bulk (one per line)</label>
        <button type="button" class="btn btn-danger h-9 text-xs ml-auto" data-remove-row>Remove</button>
    </div>
</div>
