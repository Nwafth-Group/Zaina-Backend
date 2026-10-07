<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

// Fetch all admins
$admins = User::whereIn('type', ['super_admin', 'operation'])->get();

if ($admins->isEmpty()) {
    echo "⚠️ No admin users found in database.\n";
    exit(1);
}

$time = now()->format('h:i:s A');
$title = "🔔 إشعار فوري جديد ({$time})";
$message = "تم إنشاء حجز جديد برقم مرجعي REF-" . strtoupper(Str::random(5)) . " يحتاج إلى مراجعة وتعيين عمالة.";

echo "--------------------------------------------------------\n";
echo "Sending test notification to " . $admins->count() . " admin(s)...\n";
echo "--------------------------------------------------------\n";

foreach ($admins as $admin) {
    $notificationId = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\Notifications\AdminBookingEventNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $admin->id,
        'data' => json_encode([
            'title' => $title,
            'message' => $message,
            'body' => $message,
            'event' => 'created',
            'type' => 'admin_booking_event',
        ], JSON_UNESCAPED_UNICODE),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo "✅ Sent to: {$admin->name} ({$admin->email})\n";
}

echo "--------------------------------------------------------\n";
echo "🎉 Done! Look at your Admin browser tab now:\n";
echo "1. The Toast notification will pop up in real time.\n";
echo "2. The notification bell counter will increment instantly.\n";
echo "--------------------------------------------------------\n";
