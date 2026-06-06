<?php

namespace App\Mail;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingPostReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Post $post) {}

    public function envelope(): Envelope
    {
        $title = mb_substr((string) $this->post->title, 0, 80);

        return new Envelope(
            subject: 'إعلان جديد بانتظار المراجعة #'.$this->post->id.' — '.$title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pending-post-review',
            with: [
                'post' => $this->post,
                'reviewUrl' => url('/back-office-v1/pending-post-reviews'),
                'viewUrl' => url('/back-office-v1/posts/'.$this->post->id),
            ],
        );
    }
}
