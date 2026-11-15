<?php

namespace App\Mail;

use App\Models\MailCampaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class CustomerGroupMail extends Mailable
{
    use Queueable;

    public function __construct(
        private readonly MailCampaign $campaign,
        private readonly User $recipient,
        private readonly bool $includeUnsubscribe = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        $nameToken = 'UNLOCKHUB_CUSTOMER_NAME_9D66';
        $siteToken = 'UNLOCKHUB_SITE_NAME_2A51';
        $markdown = str_replace(
            ['{name}', '{site}'],
            [$nameToken, $siteToken],
            $this->campaign->body
        );
        $bodyHtml = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        $bodyHtml = str_replace(
            [$nameToken, $siteToken],
            [e($this->recipient->name ?: $this->recipient->username), e((string) setting('site.name', config('app.name')))],
            $bodyHtml
        );

        return new Content(
            view: 'mail.customer-group',
            with: [
                'siteName' => setting('site.name', config('app.name')),
                'heading' => $this->campaign->subject,
                'preheader' => $this->campaign->preheader ?: $this->campaign->subject,
                'bodyHtml' => new HtmlString($bodyHtml),
                'unsubscribeUrl' => $this->includeUnsubscribe
                    ? URL::temporarySignedRoute(
                        'email.preferences.unsubscribe.form',
                        now()->addYear(),
                        ['user' => $this->recipient->id]
                    )
                    : null,
            ],
        );
    }
}
