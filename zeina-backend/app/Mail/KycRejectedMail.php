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

class KycRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $customer,
        public string $reason,
        public ?CompanyInfo $companyInfo = null
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->companyInfo?->name_ar ?? 'زينة للخدمات المنزلية';

        return new Envelope(
            subject: "تحديث بشأن طلب توثيق حسابك — {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kyc_rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
