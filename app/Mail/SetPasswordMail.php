<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set your Bellhop password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.set-password',
            with: [
                'user' => $this->user,
                'url' => url(route('password.reset', ['token' => $this->token, 'email' => $this->user->email], false)),
            ],
        );
    }
}
