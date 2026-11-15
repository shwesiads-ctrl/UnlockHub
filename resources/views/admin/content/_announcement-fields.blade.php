<div>
    <label for="{{ $p }}_msg" class="label text-xs">Message</label>
    <input id="{{ $p }}_msg" name="message" value="{{ $a->message }}" maxlength="255" class="input h-10" placeholder="e.g. Samsung FRP server is back online" required>
</div>
<div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(160px,1fr))]">
    <div>
        <label for="{{ $p }}_link" class="label text-xs">Link <span class="font-normal text-muted">(optional)</span></label>
        <input id="{{ $p }}_link" name="link" type="url" value="{{ $a->link }}" class="input h-10">
    </div>
    <div>
        <label for="{{ $p }}_style" class="label text-xs">Colour</label>
        <select id="{{ $p }}_style" name="style" class="input h-10">
            @foreach (['info' => 'Blue (info)', 'success' => 'Green', 'warning' => 'Amber (warning)', 'danger' => 'Red (urgent)'] as $k => $l)
                <option value="{{ $k }}" @selected($a->style === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $p }}_start" class="label text-xs">Starts</label>
        <input id="{{ $p }}_start" name="starts_at" type="datetime-local" value="{{ $a->starts_at?->format('Y-m-d\TH:i') }}" class="input h-10">
    </div>
    <div>
        <label for="{{ $p }}_end" class="label text-xs">Ends</label>
        <input id="{{ $p }}_end" name="ends_at" type="datetime-local" value="{{ $a->ends_at?->format('Y-m-d\TH:i') }}" class="input h-10">
    </div>
</div>
<label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($a->is_active)>Active</label>
