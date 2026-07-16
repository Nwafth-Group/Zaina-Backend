<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\RewardTasks;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PointsController extends Controller
{
    use ApiResponseTrait;

    public function summary()
    {
        $user = auth()->user();

        if (empty($user->referral_code)) {
            $user->referral_code = \App\Models\User::generateUniqueReferralCode();
            $user->saveQuietly();
        }

        // 1. حساب إجمالي النقاط الحالي للعميل
        $totalPoints = \App\Models\PointTransaction::getUserPointsBalance($user->id);

        // Fetch active rules from DB to compute target steps and reward points dynamically
        $bookingRule = RewardTasks::where('status', true)->where('rule_category', 'booking')->first();
        $bookingCountRule = RewardTasks::where('status', true)->where('rule_category', 'booking_count')->first();
        $referralRule = RewardTasks::where('status', true)->where('rule_category', 'referral')->first();

        // Completed bookings count
        $completedCount = $user->bookings()->where('status', 'completed')->count();

        $progressCards = [];

        // 🌟 A. Referral Card
        $referralCount = \App\Models\User::where('referred_by_id', $user->id)->count();
        $successfulReferralCount = \App\Models\User::where('referred_by_id', $user->id)
            ->where('verification_status', 'approved')
            ->count();
        
        $referralPoints = $user->pointTransactions()
            ->where('reason', 'referral_approved')
            ->sum('amount');

        if ($referralRule) {
            $progressCards[] = [
                'title' => app()->getLocale() == 'ar' ? ($referralRule->title_ar ?? 'دعوة الأصدقاء') : ($referralRule->title_en ?? 'Invite Friends'),
                'current_step' => $successfulReferralCount,
                'target_step' => $successfulReferralCount + 1,
                'steps_remaining' => 1,
                'is_completed' => false,
                'reward_points' => (int) $referralRule->points,
                'action_type' => 'invite_friend',
                'footer_text' => app()->getLocale() == 'ar'
                    ? "ادع صديقاً واحصل على {$referralRule->points} نقطة"
                    : "Invite a friend and get {$referralRule->points} points",
            ];
        }

        // 🌟 B. Booking Card
        if ($bookingRule) {
            $bookingPoints = $bookingRule->points ?? 50;
            $bookingFrequency = $bookingRule->frequency_type ?? 'repeatable';
            $bookingCurrent = 0;
            $bookingCompleted = false;

            if ($bookingFrequency === 'one_time') {
                if ($completedCount >= 1) {
                    $bookingCurrent = 1;
                    $bookingCompleted = true;
                }
            } else {
                $bookingCurrent = 0;
            }

            $progressCards[] = [
                'title' => app()->getLocale() == 'ar' ? ($bookingRule->title_ar ?? 'إتمام خدمة') : ($bookingRule->title_en ?? 'Complete Booking'),
                'current_step' => $bookingCurrent,
                'target_step' => 1,
                'steps_remaining' => $bookingCompleted ? 0 : 1,
                'is_completed' => $bookingCompleted,
                'reward_points' => (int) $bookingPoints,
                'action_type' => 'complete_booking',
                'footer_text' => app()->getLocale() == 'ar'
                    ? "اكسب {$bookingPoints} نقطة عند إتمام الحجز"
                    : "Earn {$bookingPoints} points upon booking completion",
            ];
        }

        // 🌟 C. Booking Count Card
        $bookingCountEnabled = false;
        $targetServices = 3;
        $bookingCountPoints = 100;
        $bookingCountFrequency = 'repeatable';

        if ($bookingCountRule) {
            $bookingCountEnabled = true;
            $targetServices = (int) ($bookingCountRule->required_booking_count ?? 3);
            $bookingCountPoints = (int) ($bookingCountRule->points ?? 100);
            $bookingCountFrequency = $bookingCountRule->frequency_type ?? 'repeatable';
        } else {
            $pointSetting = DB::table('point_settings')->where('key', 'service_cycle_rules')->first();
            if ($pointSetting && !empty($pointSetting->value)) {
                $decodedSetting = json_decode($pointSetting->value, true);
                if (isset($decodedSetting['enabled']) && $decodedSetting['enabled']) {
                    $bookingCountEnabled = true;
                    $targetServices = (int) ($pointSetting->target_steps ?? 3);
                    $bookingCountPoints = (int) ($pointSetting->reward_points ?? 100);
                }
            }
        }

        if ($bookingCountEnabled) {
            $cycleCurrent = 0;
            $cycleCompleted = false;

            if ($bookingCountFrequency === 'one_time') {
                $cycleCurrent = min($completedCount, $targetServices);
                $cycleCompleted = ($cycleCurrent >= $targetServices);
            } else {
                $cycleCurrent = $completedCount % $targetServices;
            }

            $remaining = max($targetServices - $cycleCurrent, 0);
            $footerText = app()->getLocale() == 'ar'
                ? ($remaining > 0 ? "تبقى {$remaining} خطوات لكسب نقاط المكافأة الخاصة بك." : "لقد حصلت على نقاط المكافأة الخاصة بك!")
                : ($remaining > 0 ? "{$remaining} steps remaining to earn your reward points." : "You have earned your reward points!");

            $progressCards[] = [
                'title' => app()->getLocale() == 'ar'
                    ? ($bookingCountRule->title_ar ?? "إتمام {$targetServices} خدمات")
                    : ($bookingCountRule->title_en ?? "Complete {$targetServices} Bookings"),
                'current_step' => $cycleCurrent,
                'target_step' => $targetServices,
                'steps_remaining' => $remaining,
                'is_completed' => $cycleCompleted,
                'reward_points' => $bookingCountPoints,
                'action_type' => 'complete_bookings',
                'footer_text' => $footerText,
            ];
        }

        // 3. جلب مهام كسب النقاط الديناميكية (Reward Tasks) - Only social media tasks in summary list
        $completedTaskIds = DB::table('user_reward_tasks')
            ->where('user_id', $user->id)
            ->pluck('task_id')
            ->toArray();

        $tasks = RewardTasks::where('status', true)
            ->where('rule_category', 'social_media')
            ->get()
            ->take(3)
            ->map(function ($task) use ($completedTaskIds) {
                return [
                    'id' => $task->id,
                    'title' => app()->getLocale() == 'ar' ? $task->title_ar : $task->title_en,
                    'subtitle' => app()->getLocale() == 'ar' ? $task->subtitle_ar : $task->subtitle_en,
                    'points' => '+'.$task->points.' Pts',
                    'action_type' => $task->action_type,
                    'action_link' => $task->action_link,
                    'rule_category' => $task->rule_category,
                    'is_completed' => in_array($task->id, $completedTaskIds),
                ];
            });

        // 4. جلب آخر 3 حركات في سجل النقاط
        $recentHistory = $user->pointTransactions()
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'title' => app()->getLocale() == 'ar' ? $tx->title_ar : $tx->title_en,
                    'amount' => ($tx->type == 'earn' ? '+' : '-').$tx->amount.' Pts',
                    'date' => $tx->created_at->format('d M. Y'),
                ];
            });

        $companyInfo = \App\Models\CompanyInfo::first();
        $googlePlayLink = $companyInfo->google_play_link ?? null;
        $appStoreLink = $companyInfo->app_store_link ?? null;
        $appNameAr = $companyInfo->name_ar ?? 'زينه';
        $appNameEn = $companyInfo->name_en ?? 'Ziena';

        $shareMessageAr = "حملي تطبيق {$appNameAr} واستخدمي كود الإحالة الخاص بي: {$user->referral_code} للحصول على نقاط مكافأة! رابط أندرويد: {$googlePlayLink} رابط آيفون: {$appStoreLink}";
        $shareMessageEn = "Download {$appNameEn} App and use my referral code: {$user->referral_code} to get reward points! Android: {$googlePlayLink} iOS: {$appStoreLink}";

        return $this->successResponse([
            'total_points' => $totalPoints,
            'referral_code' => $user->referral_code,
            'referral_count' => $referralCount,
            'successful_referral_count' => $successfulReferralCount,
            'google_play_link' => $googlePlayLink,
            'app_store_link' => $appStoreLink,
            'share_message_ar' => $shareMessageAr,
            'share_message_en' => $shareMessageEn,
            'progress_card' => $progressCards,
            'earn_points' => $tasks,
            'history' => $recentHistory,
        ], __('messages.points.summary_retrieved'));
    }

    public function history(Request $request)
    {
        $user = auth()->user();
        $query = $user->pointTransactions()->latest();
        if ($request->has('filter')) {
            $filter = $request->filter;

            switch ($filter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'last_7_days':
                    $query->where('created_at', '>=', Carbon::now()->subDays(7));
                    break;
                case 'last_30_days':
                    $query->where('created_at', '>=', Carbon::now()->subDays(30));
                    break;
                case 'custom':
                    $query->whereBetween('created_at', [
                        $request->start_date.' 00:00:00',
                        $request->end_date.' 23:59:59',
                    ]);
                    break;
            }
        }

        // نعمل Pagination عشان لو الـ History طويل ما يعلق الموبايل
        $history = $query->paginate(15);

        // تجهيز الداتا للـ Resource
        $formattedHistory = $history->getCollection()->map(function ($tx) {
            $lang = app()->getLocale();

            return [
                'id' => $tx->id,
                'type' => $tx->type, // 'earn' عشان يعطيه سهم أخضر لفوق، 'redeem' سهم أحمر لتحت
                'type_translate' => __("messages.{$tx->type}"),
                'title' => $lang == 'en' ? $tx->title_en : $tx->title_ar,
                'amount' => $tx->formatted_amount,
                'date' => $tx->created_at->format('d M. Y'),
            ];
        });

        return $this->successResponse($formattedHistory, __('messages.points.history_retrieved'));
    }

    public function completeTask(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:reward_tasks,id',
        ]);

        $user = auth()->user();
        $task = RewardTasks::findOrFail($request->task_id);

        // حماية تجريبية: نمنع التكرار إذا كانت المهمة من النوع الذي ينجر لمرة واحدة فقط
        if ($task->action_type === 'url') {
            $alreadyCompleted = DB::table('user_reward_tasks')
                ->where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->exists();

            if ($alreadyCompleted) {
                return $this->errorResponse(__('messages.points.task_already_completed'), 422);
            }
        }

        DB::beginTransaction();
        try {
            // 1. تسجيل المهمة كمكتملة للمستخدم
            DB::table('user_reward_tasks')->insert([
                'user_id' => $user->id,
                'task_id' => $task->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. إضافة النقاط بجدول الحركات (Transactions)
            $user->pointTransactions()->create([
                'type' => 'earn',
                'amount' => $task->points,
                'title_ar' => 'مكافأة إتمام مهمة: '.$task->title_ar,
                'title_en' => 'Task Reward: '.$task->title_en,
            ]);

            DB::commit();

            return $this->successResponse(null, __('messages.points.task_completed_and_points_earned', ['points' => $task->points]));

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('حدث خطأ: '.$e->getMessage(), 500);
        }
    }

    public function earnPoints(Request $request)
    {
        $user = auth()->user();
        $completedTaskIds = DB::table('user_reward_tasks')
            ->where('user_id', $user->id)
            ->pluck('task_id')
            ->toArray();

        $query = RewardTasks::where('status', true);

        if ($request->filled('category')) {
            $query->where('rule_category', $request->category);
        } else {
            $query->where('rule_category', 'social_media');
        }

        $tasks = $query->get()->map(function ($task) use ($completedTaskIds) {
            return [
                'id' => $task->id,
                'title' => app()->getLocale() == 'ar' ? $task->title_ar : $task->title_en,
                'subtitle' => app()->getLocale() == 'ar' ? $task->subtitle_ar : $task->subtitle_en,
                'points' => '+'.$task->points.' Pts',
                'action_type' => $task->action_type,
                'action_link' => $task->action_link,
                'rule_category' => $task->rule_category,
                'is_completed' => in_array($task->id, $completedTaskIds),
            ];
        });

        return $this->successResponse($tasks, __('messages.points.earn_points_retrieved'));
    }
}
