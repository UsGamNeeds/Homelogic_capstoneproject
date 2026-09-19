<?php

namespace App\Mail;

use App\Models\AccountDeletionRequest;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AccountDeletionRequest $deletionRequest
    ) {}

    public function envelope(): Envelope
    {
        $staffName = $this->deletionRequest->user->name;

        return new Envelope(
            subject: "Account Deletion Request from {$staffName}",
        );
    }

    public function content(): Content
    {
        $staff = $this->deletionRequest->user;
        $staffName = $staff->name;

        return new Content(
            text: 'mail.account-deletion-request',
            with: [
                'staffName' => $staffName,
                'staffEmail' => $staff->email ?? 'Unknown',
                'staffRole' => $staff->role ?? 'Unknown',
                'reason' => $this->deletionRequest->reason,
                'requestedAt' => $this->deletionRequest->created_at
                    ? Carbon::parse($this->deletionRequest->created_at)->format('M d, Y g:i A')
                    : 'Unknown',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
