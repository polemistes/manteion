<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One line per fact and a link, to an administrator who asked to hear
 * about this kind of thing (App\Services\AdminAlerts).
 */
class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $subjectLine,
        public array $lines,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin-alert',
            with: ['lines' => $this->lines, 'url' => $this->url],
        );
    }
}
