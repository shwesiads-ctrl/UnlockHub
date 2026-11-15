<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCustomerGroupMailChunk;
use App\Mail\CustomerGroupMail;
use App\Models\ActivityLog;
use App\Models\MailCampaign;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Illuminate\View\View;

class MailCampaignController extends Controller
{
    private const CHUNK_SIZE = 25;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:user_groups,id'],
        ]);
        $groupId = isset($filters['group_id']) ? (int) $filters['group_id'] : null;

        return view('admin.mail-campaigns.index', [
            'groups' => UserGroup::orderBy('sort')->orderBy('name')->get(['id', 'name']),
            'selectedGroupId' => $groupId,
            'audienceCount' => $this->recipients($groupId)->count(),
            'campaigns' => MailCampaign::with(['group:id,name', 'creator:id,username'])
                ->latest('id')->paginate(15)->withQueryString(),
            'mailDriver' => config('mail.default'),
            'queueConnection' => config('queue.default'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:user_groups,id'],
            'subject' => ['required', 'string', 'max:160'],
            'preheader' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:15000'],
            'confirm_send' => ['required', 'accepted'],
        ]);

        if (config('mail.default') === 'log' || config('mail.default') === 'array') {
            return back()->withInput()->with('error', 'Configure a real mail transport in .env before sending a customer campaign.');
        }
        if (config('queue.default') === 'sync') {
            return back()->withInput()->with('error', 'Set QUEUE_CONNECTION=database (or another asynchronous queue) before sending group campaigns.');
        }

        $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
        $recipients = $this->recipients($groupId);
        $total = (clone $recipients)->count();
        if ($total === 0) {
            return back()->withInput()->with('error', 'No active customers in this audience have a verified email address and email updates enabled.');
        }

        $subject = trim((string) preg_replace('/[\r\n]+/', ' ', $data['subject']));
        $campaign = MailCampaign::create([
            'user_group_id' => $groupId,
            'created_by' => $request->user()->id,
            'subject' => $subject,
            'preheader' => trim((string) ($data['preheader'] ?? '')) ?: null,
            'body' => trim($data['body']),
            'status' => 'queued',
            'recipient_count' => $total,
            'pending_chunks' => (int) ceil($total / self::CHUNK_SIZE),
        ]);

        $recipients->select('id')->orderBy('id')->chunkById(self::CHUNK_SIZE, function ($users) use ($campaign) {
            SendCustomerGroupMailChunk::dispatch($campaign->id, $users->pluck('id')->map(fn ($id) => (int) $id)->all());
        });

        ActivityLog::record('mail_campaign.queued', $campaign, [
            'group_id' => $groupId,
            'recipients' => $total,
        ]);

        return redirect()->route('admin.mail-campaigns.index')
            ->with('success', "Campaign queued for {$total} eligible customer(s). Queue workers must be running to deliver it.");
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'preheader' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:15000'],
        ]);
        $admin = $request->user();
        if (! $admin->email) {
            return back()->withInput()->with('error', 'Add an email address to your admin account before sending a preview.');
        }

        $campaign = new MailCampaign([
            'subject' => trim((string) preg_replace('/[\r\n]+/', ' ', $data['subject'])),
            'preheader' => $data['preheader'] ?? null,
            'body' => trim($data['body']),
        ]);

        try {
            Mail::to($admin->email)->send(new CustomerGroupMail($campaign, $admin, false));
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Preview could not be sent. Check the mail transport settings.');
        }

        $message = config('mail.default') === 'log'
            ? 'Preview was rendered to the log mail driver. Configure SMTP to receive it in your inbox.'
            : 'Preview email sent to '.$admin->email.'.';

        return back()->withInput()->with('success', $message);
    }

    private function recipients(?int $groupId)
    {
        return User::query()
            ->where('status', 'active')
            ->where('role', '!=', User::ROLE_ADMIN)
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->where('notify_email', true)
            ->when($groupId, fn ($query) => $query->where('user_group_id', $groupId));
    }
}
