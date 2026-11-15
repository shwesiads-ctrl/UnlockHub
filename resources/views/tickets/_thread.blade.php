{{-- Ticket conversation. $messages, $ticket, $staffView (bool), $fileRoute ('account.tickets.attachment' | 'admin.tickets.attachment') --}}
<ol class="m-0 p-0 list-none flex flex-col gap-3">
    @foreach ($messages as $m)
        <li @class([
            'rounded-xl border p-4 flex flex-col gap-2',
            'bg-point-soft border-point/40' => $m->is_internal,
            'bg-brand-soft border-brand/20' => $m->is_staff && ! $m->is_internal,
            'bg-surface border-line' => ! $m->is_staff,
            'sm:ml-10' => $m->is_staff, 'sm:mr-10' => ! $m->is_staff,
        ])>
            <div class="flex flex-wrap items-center gap-2 text-[13px]">
                @if ($m->author && ($staffView || ! $m->is_staff)) <x-avatar :user="$m->author" :size="28" /> @endif
                <strong>{{ $m->is_staff && ! $staffView ? __('Support team') : ($m->author?->name ?? 'Deleted user') }}</strong>
                @if ($m->is_staff && $staffView) <span class="badge badge-info">{{ __('Staff') }}</span> @endif
                @if ($m->is_internal) <span class="badge badge-pending">{{ __('Internal note — customer can\'t see') }}</span> @endif
                <span class="ml-auto text-muted">{{ $m->created_at->format('d M Y H:i') }}</span>
            </div>
            <div class="text-sm leading-relaxed whitespace-pre-line break-words">{{ $m->body }}</div>
            @if ($m->attachments->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($m->attachments as $a)
                        <a href="{{ route($fileRoute, [$ticket, $a]) }}" target="_blank" rel="noopener" class="btn h-9 text-xs">
                            <x-icon name="file" size="14" />{{ \Illuminate\Support\Str::limit($a->original_name, 30) }} <span class="text-muted">{{ number_format($a->size / 1024) }} KB</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </li>
    @endforeach
</ol>
