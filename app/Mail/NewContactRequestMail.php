<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewContactRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactRequest $contactRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'طلب تواصل جديد #'.$this->contactRequest->id.' — '.$this->contactRequest->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-request-received',
            with: [
                'contactRequest' => $this->contactRequest,
                'panelUrl' => url('/back-office-v1/contact-requests'),
            ],
        );
    }
}
