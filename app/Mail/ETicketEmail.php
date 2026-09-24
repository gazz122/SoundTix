<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ETicketEmail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $ticket
     * @param  array<string, mixed>  $order
     */
    public function __construct(
        public array $ticket,
        public array $order,
        public string $qrPng,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'E-Tiket SoundTix - '.$this->ticket['event_title']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket');
    }
}
