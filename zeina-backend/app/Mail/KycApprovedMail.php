<?php

namespace App\Mail;

use App\Models\CompanyInfo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KycApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $customer,
        public ?CompanyInfo $companyInfo = null
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->companyInfo?->name_ar ?? 'زينة للخدمات المنزلية';

        return new Envelope(
            subject: "تمت الموافقة على توثيق حسابك — {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kyc_approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
