<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One simple Arabic e-mail: a heading, a few lines, an optional button and a small note. */
class NoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $heading,
        public array $lines,
        public ?string $buttonText = null,
        public ?string $buttonUrl = null,
        public ?string $note = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notice');
    }
}
