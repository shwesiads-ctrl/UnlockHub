<?php

namespace App\Jobs;

use App\Mail\CustomerGroupMail;
use App\Models\MailCampaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCustomerGroupMailChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** @param int[] $userIds */
    public function __construct(
        public readonly int $campaignId,
        public readonly array $userIds,
    ) {}

    public function handle(): void
    {
        $campaign = MailCampaign::find($this->campaignId);
        if (! $campaign || in_array($campaign->status, ['sent', 'completed_with_errors', 'failed'], true)) {
            return;
        }

        MailCampaign::whereKey($campaign->id)->where('status', 'queued')->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);

        foreach ($this->userIds as $id) {
            $query = User::query()
                ->whereKey($id)
                ->where('status', 'active')
                ->where('role', '!=', User::ROLE_ADMIN)
                ->whereNotNull('email')
                ->whereNotNull('email_verified_at')
                ->where('notify_email', true);

            if ($campaign->user_group_id) {
                $query->where('user_group_id', $campaign->user_group_id);
            }

            $user = $query->first();
            if (! $user) {
                $campaign->increment('skipped_count');
                continue;
            }

            try {
                Mail::to($user->email)->send(new CustomerGroupMail($campaign, $user));
                $campaign->increment('sent_count');
            } catch (Throwable $e) {
                report($e);
                $campaign->increment('failed_count');
            }
        }

        $this->finishChunk();
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            report($exception);
        }
        $campaign = MailCampaign::find($this->campaignId);
        if (! $campaign) {
            return;
        }

        DB::transaction(function () use ($campaign) {
            $locked = MailCampaign::whereKey($campaign->id)->lockForUpdate()->first();
            if (! $locked || $locked->pending_chunks < 1) {
                return;
            }

            $locked->failed_count += count($this->userIds);
            $locked->pending_chunks--;
            $locked->status = $locked->pending_chunks > 0 ? 'sending' : 'completed_with_errors';
            if ($locked->pending_chunks === 0) {
                $locked->completed_at = now();
            }
            $locked->save();
        });
    }

    private function finishChunk(): void
    {
        DB::transaction(function () {
            $campaign = MailCampaign::whereKey($this->campaignId)->lockForUpdate()->first();
            if (! $campaign || $campaign->pending_chunks < 1) {
                return;
            }

            $campaign->pending_chunks--;
            if ($campaign->pending_chunks === 0) {
                $campaign->status = $campaign->failed_count > 0 ? 'completed_with_errors' : 'sent';
                $campaign->completed_at = now();
            }
            $campaign->save();
        });
    }
}
