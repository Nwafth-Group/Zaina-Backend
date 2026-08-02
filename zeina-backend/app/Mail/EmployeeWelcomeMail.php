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

class EmployeeWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $employee,
        public ?string $plainPassword = null,
        public ?CompanyInfo $companyInfo = null,
        public ?string $adminPortalUrl = null,
        public bool $isUpdate = false
    ) {
        $this->adminPortalUrl = $adminPortalUrl ?: config('app.admin_portal_url', '#');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = $this->companyInfo?->name_ar ?? 'زينة للخدمات المنزلية';

        $subject = $this->isUpdate
            ? "تحديث بيانات حسابك الإداري — {$companyName}"
            : "مرحباً بك في فريق {$companyName} — بيانات حسابك الإداري";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.employee_welcome',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
