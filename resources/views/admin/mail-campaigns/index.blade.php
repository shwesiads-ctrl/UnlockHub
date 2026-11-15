@extends('layouts.admin', ['title' => 'Customer emails', 'subtitle' => 'Write and deliver a branded message to customer groups'])

@section('content')
<div class="flex flex-col gap-6">
    <section class="card p-5 flex flex-wrap items-center gap-4">
        <div class="min-w-0 flex-1">
            <span class="badge badge-info">Group mailing</span>
            <h2 class="m-0 mt-2 text-xl font-bold">One campaign, one customer group</h2>
            <p class="m-0 mt-1 text-sm text-muted">Each recipient receives a separate email. Addresses stay private; opted-out, unverified, suspended and admin accounts are excluded.</p>
        </div>
        <div class="rounded-xl border border-line bg-surface-2 px-4 py-3 text-sm">
            <span class="block text-xs text-muted">Mail / queue</span>
            <strong>{{ strtoupper($mailDriver) }}</strong><span class="text-muted"> · {{ $queueConnection }}</span>
        </div>
    </section>

    @if ($mailDriver === 'log' || $mailDriver === 'array')
        <div class="badge-pending rounded-xl px-4 py-3 text-sm">A real SMTP or API mail transport is required before sending. Current mail driver: <strong>{{ $mailDriver }}</strong>.</div>
    @endif
    @if ($queueConnection === 'sync')
        <div class="badge-pending rounded-xl px-4 py-3 text-sm">Set <code>QUEUE_CONNECTION=database</code> and run a queue worker or scheduler before sending group campaigns. Synchronous delivery is disabled to avoid web request timeouts.</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(300px,.75fr)] items-start">
        <section class="card p-5 sm:p-6 flex flex-col gap-5">
            <div class="settings-heading">
                <span class="badge badge-info">Composer</span>
                <h2>New customer email</h2>
                <p>Write in Markdown. The responsive email design adds your site name and a signed unsubscribe link.</p>
            </div>

            <form method="GET" action="{{ route('admin.mail-campaigns.index') }}" class="rounded-xl border border-line bg-surface-2 p-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <label for="audience_group" class="label">Preview audience</label>
                    <select id="audience_group" name="group_id" class="input h-11">
                        <option value="">All customer groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected($selectedGroupId === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn">Preview recipients</button>
                <span class="text-sm font-semibold text-brand-ink">{{ number_format($audienceCount) }} eligible</span>
            </form>

            <form method="POST" action="{{ route('admin.mail-campaigns.send') }}" class="flex flex-col gap-4" onsubmit="return event.submitter?.name === 'preview_campaign' || confirm('Queue this email for {{ number_format($audienceCount) }} eligible customer(s)?')">
                @csrf
                @if ($selectedGroupId)<input type="hidden" name="group_id" value="{{ $selectedGroupId }}">@endif
                <div><label for="mail_subject" class="label">Subject</label><input id="mail_subject" name="subject" maxlength="160" required value="{{ old('subject') }}" class="input h-11" placeholder="A clear subject customers recognize"><x-field-error name="subject" /></div>
                <div><label for="mail_preheader" class="label">Inbox preview text <span class="font-normal text-muted">(optional)</span></label><input id="mail_preheader" name="preheader" maxlength="180" value="{{ old('preheader') }}" class="input h-11" placeholder="A short summary shown beside the subject"><x-field-error name="preheader" /></div>
                <div>
                    <label for="mail_body" class="label">Message</label>
                    <textarea id="mail_body" name="body" rows="12" maxlength="15000" required class="input min-h-64 py-3 font-mono text-sm" placeholder="Hello {name},&#10;&#10;Write your announcement here. Use **bold**, lists, and [links](https://example.com).&#10;&#10;Thank you,&#10;{site}">{{ old('body') }}</textarea>
                    <p class="m-0 mt-1 text-xs text-muted">Personalization: <code>{name}</code> and <code>{site}</code>. HTML is escaped; unsafe links are removed.</p>
                    <x-field-error name="body" />
                </div>
                <label class="flex items-start gap-2.5 text-sm"><input type="checkbox" name="confirm_send" value="1" required class="mt-0.5 h-4 w-4 accent-[var(--uh-brand)]"><span>I reviewed the audience and content. Send only to active customers with verified email and email updates enabled.</span></label>
                <x-field-error name="confirm_send" />
                <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                    <button type="submit" name="preview_campaign" value="1" formaction="{{ route('admin.mail-campaigns.preview') }}" formmethod="POST" formnovalidate class="btn">Send preview to my email</button>
                    <button type="submit" name="send_campaign" value="1" class="btn btn-primary" @disabled($audienceCount < 1 || in_array($mailDriver, ['log', 'array'], true) || $queueConnection === 'sync')>Queue email for {{ number_format($audienceCount) }} recipients</button>
                    <span class="text-xs text-muted">The queue worker or configured scheduler must be running to deliver messages.</span>
                </div>
            </form>
        </section>

        <aside class="flex flex-col gap-5">
            <section class="card p-5 flex flex-col gap-4">
                <div><span class="badge badge-info">Email design</span><h2 class="m-0 mt-2 text-lg font-bold">UnlockHub campaign</h2><p class="m-0 mt-1 text-sm text-muted">Responsive, single-column layout for mobile and desktop.</p></div>
                <div class="rounded-2xl border border-line bg-[#f3f5f9] p-3 sm:p-5">
                    <div class="mx-auto max-w-sm overflow-hidden rounded-xl border border-line bg-white">
                        <div class="bg-brand px-5 py-4 text-sm font-bold text-white">{{ setting('site.name', config('app.name')) }}</div>
                        <div class="p-5">
                            <span class="block h-2 w-20 rounded bg-surface-2"></span>
                            <span class="mt-4 block h-4 w-4/5 rounded bg-surface-2"></span>
                            <span class="mt-3 block h-2 w-full rounded bg-surface-2"></span>
                            <span class="mt-2 block h-2 w-11/12 rounded bg-surface-2"></span>
                            <span class="mt-2 block h-2 w-3/4 rounded bg-surface-2"></span>
                            <span class="mt-5 inline-block rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white">Message content</span>
                        </div>
                        <div class="border-t border-line px-5 py-4 text-[11px] text-muted">Email preferences · Unsubscribe link</div>
                    </div>
                </div>
                <ul class="m-0 pl-5 text-sm text-muted flex flex-col gap-2">
                    <li>Individual delivery keeps customer addresses private.</li>
                    <li>Signed unsubscribe page turns off customer email updates.</li>
                    <li>Queue progress and delivery errors appear below.</li>
                </ul>
            </section>
        </aside>
    </div>

    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line"><div><h2 class="m-0 text-lg font-bold">Campaign history</h2><p class="m-0 mt-1 text-xs text-muted">Queued messages and delivery progress.</p></div></div>
        @if ($campaigns->isEmpty())
            <p class="m-0 px-5 py-8 text-sm text-muted">No campaigns yet.</p>
        @else
            <div class="table-wrap"><table class="table min-w-[760px]">
                <thead><tr><th>Created</th><th>Subject</th><th>Audience</th><th>Status</th><th class="text-right">Sent / eligible</th><th class="text-right">Failed</th></tr></thead>
                <tbody>
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td class="whitespace-nowrap">{{ $campaign->created_at->format('d M Y H:i') }}<span class="block text-xs text-muted">{{ $campaign->creator?->username ?? 'admin' }}</span></td>
                            <td class="font-semibold">{{ $campaign->subject }}</td>
                            <td>{{ $campaign->user_group_id ? ($campaign->group?->name ?? 'Deleted group') : 'All customer groups' }}</td>
                            <td><span class="badge {{ in_array($campaign->status, ['sent', 'completed_with_errors']) ? ($campaign->failed_count ? 'badge-pending' : 'badge-success') : 'badge-info' }}">{{ str_replace('_', ' ', ucfirst($campaign->status)) }}</span>@if ($campaign->pending_chunks)<span class="block mt-1 text-xs text-muted">{{ $campaign->pending_chunks }} queue chunk(s)</span>@endif</td>
                            <td class="text-right whitespace-nowrap">{{ number_format($campaign->sent_count + $campaign->skipped_count) }} / {{ number_format($campaign->recipient_count) }}</td>
                            <td class="text-right">{{ number_format($campaign->failed_count) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <div class="px-5 py-3">{{ $campaigns->links('partials.numbered-pagination') }}</div>
        @endif
    </section>
</div>
@endsection
