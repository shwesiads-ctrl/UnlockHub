<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class StatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Carbon $from,
        public Carbon $to,
        public float $opening,
        public float $closing,
        public float $credits,
        public float $debits,
        public string $pdf,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        $site = (string) setting('site.name', config('app.name'));

        return new Envelope(subject: "{$site} statement · {$this->from->format('d M Y')} – {$this->to->format('d M Y')}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.statement');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
