<?php

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $report;

    protected $type; // 'submitted', 'status_changed'

    protected $title;

    protected $body;

    public function __construct($report, $type = 'submitted')
    {
        $this->report = $report;
        $this->type = $type;

        if ($type === 'submitted') {
            $this->title = 'New Booking Report: '.$report->report_number;
            $this->body = 'A new issue report was submitted by client for booking #'.($report->booking->booking_ref ?? $report->booking_id);
        } elseif ($type === 'assigned') {
            $this->title = __('messages.report_notification_title_assigned', ['number' => $report->report_number]);
            $this->body = __('messages.report_notification_body_assigned', ['number' => $report->report_number]);
        } elseif ($type === 'note_added') {
            $this->title = __('messages.report_notification_title_note_added', ['number' => $report->report_number]);
            $this->body = __('messages.report_notification_body_note_added');
        } else {
            $translatedStatus = __('messages.report_status_'.$report->status);
            $this->title = __('messages.report_notification_title_status_changed', ['number' => $report->report_number]);
            $this->body = __('messages.report_notification_body_status_changed', ['status' => $translatedStatus]);
        }
    }

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        Log::info('--- [Report Notification] Storing in Database ---');

        return [
            'booking_id' => $this->report->booking_id,
            'report_id' => $this->report->id,
            'report_number' => $this->report->report_number,
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => [
                'status' => $this->report->status,
                'reason' => $this->report->reason,
            ],
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        Log::info('--- [Report Notification] Preparing Firebase Push ---');

        return [
            'title' => $this->title,
            'body' => $this->body,
            'extra_data' => [
                'booking_id' => (string) $this->report->booking_id,
                'report_id' => (string) $this->report->id,
                'report_number' => (string) $this->report->report_number,
                'action_type' => 'open_report_details',
            ],
        ];
    }
}
