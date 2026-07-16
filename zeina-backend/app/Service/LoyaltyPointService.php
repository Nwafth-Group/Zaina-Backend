<?php

namespace App\Service;

use App\Models\Booking;
use App\Models\User;
use App\Models\RewardTasks;
use App\Models\PointTransaction;
use App\Notifications\PointsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyPointService
{
    /**
     * Allocate points when a booking status transitions to 'completed'.
     */
    public static function awardPointsForCompletedBooking(Booking $booking): void
    {
        try {
            $booking->load(['customer']);
            $customer = $booking->customer;
            if (!$customer) {
                return;
            }

            DB::transaction(function () use ($booking, $customer) {
                // 🌟 1. Booking Completed Reward
                $alreadyAwardedBooking = PointTransaction::where('user_id', $customer->id)
                    ->where('reference_type', Booking::class)
                    ->where('reference_id', $booking->id)
                    ->where('type', 'earn')
                    ->where('reason', 'booking_completed')
                    ->exists();

                if (!$alreadyAwardedBooking) {
                    $bookingTask = RewardTasks::where('status', true)
                        ->where('rule_category', 'booking')
                        ->where('trigger_event', 'booking_completed')
                        ->first();

                    if ($bookingTask) {
                        $customer->pointTransactions()->create([
                            'type' => 'earn',
                            'amount' => $bookingTask->points,
                            'title_ar' => 'مكافأة إتمام الحجز رقم: ' . $booking->booking_ref,
                            'title_en' => 'Completed Booking Reward: #' . $booking->booking_ref,
                            'reference_type' => Booking::class,
                            'reference_id' => $booking->id,
                            'reason' => 'booking_completed',
                        ]);

                        $customer->notify(new PointsNotification(
                            'حصلت على نقاط جديدة! 🎉',
                            'تمت إضافة ' . $bookingTask->points . ' نقطة إلى رصيدك لإتمامك الخدمة بنجاح.',
                            $bookingTask->points,
                            'earn',
                            ['booking_ref' => $booking->booking_ref]
                        ));

                        Log::info("BookingCompleted points awarded: {$bookingTask->points} points to user ID {$customer->id} for booking ID {$booking->id}.");
                    }
                }

                // 🌟 2. Services Cycle Progress (Complete X services to earn Y points)
                $bookingCountRule = RewardTasks::where('status', true)
                    ->where('rule_category', 'booking_count')
                    ->first();

                $targetSteps = 0;
                $rewardPoints = 0;
                $frequencyType = 'repeatable';

                if ($bookingCountRule) {
                    $targetSteps = (int) ($bookingCountRule->required_booking_count ?? 3);
                    $rewardPoints = (int) ($bookingCountRule->points ?? 100);
                    $frequencyType = $bookingCountRule->frequency_type ?? 'repeatable';
                } else {
                    $cycleSetting = DB::table('point_settings')->where('key', 'service_cycle_rules')->first();
                    if ($cycleSetting && !empty($cycleSetting->value)) {
                        $targetSteps = (int) ($cycleSetting->target_steps ?? 3);
                        $rewardPoints = (int) ($cycleSetting->reward_points ?? 100);
                    }
                }

                if ($targetSteps > 0 && $rewardPoints > 0) {
                    $completedCount = Booking::where('customer_id', $customer->id)
                        ->where('status', 'completed')
                        ->count();

                    if ($frequencyType === 'one_time') {
                        if ($completedCount >= $targetSteps) {
                            $alreadyAwardedCycle = PointTransaction::where('user_id', $customer->id)
                                ->where('reason', 'service_cycle_reward_onetime_' . $targetSteps)
                                ->exists();

                            if (!$alreadyAwardedCycle) {
                                $customer->pointTransactions()->create([
                                    'type' => 'earn',
                                    'amount' => $rewardPoints,
                                    'title_ar' => 'مكافأة إتمام عدد خدمات: ' . $targetSteps,
                                    'title_en' => 'Complete Bookings Target Reward: ' . $targetSteps,
                                    'reason' => 'service_cycle_reward_onetime_' . $targetSteps,
                                ]);

                                $customer->notify(new PointsNotification(
                                    'مكافأة التقدم في الخدمات! 🏆',
                                    'رائع! أكملت بنجاح ' . $targetSteps . ' خدمات وحصلت على ' . $rewardPoints . ' نقطة مكافأة.',
                                    $rewardPoints,
                                    'earn',
                                    ['target_steps' => $targetSteps]
                                ));

                                Log::info("ServicesCycle points (one_time) awarded: {$rewardPoints} points to user ID {$customer->id} for reaching {$targetSteps} services.");
                            }
                        }
                    } else {
                        if ($completedCount > 0 && $completedCount % $targetSteps === 0) {
                            $alreadyAwardedCycle = PointTransaction::where('user_id', $customer->id)
                                ->where('reason', 'service_cycle_reward_' . $completedCount)
                                ->exists();

                            if (!$alreadyAwardedCycle) {
                                $customer->pointTransactions()->create([
                                    'type' => 'earn',
                                    'amount' => $rewardPoints,
                                    'title_ar' => 'مكافأة إكمال دورة الخدمات (المستوى ' . ($completedCount / $targetSteps) . ')',
                                    'title_en' => 'Services Cycle Reward (Level ' . ($completedCount / $targetSteps) . ')',
                                    'reason' => 'service_cycle_reward_' . $completedCount,
                                ]);

                                $customer->notify(new PointsNotification(
                                    'مكافأة التقدم في الخدمات! 🏆',
                                    'رائع! أكملت بنجاح ' . $targetSteps . ' خدمات وحصلت على ' . $rewardPoints . ' نقطة إضافية.',
                                    $rewardPoints,
                                    'earn',
                                    ['cycle_completed_level' => ($completedCount / $targetSteps)]
                                ));

                                Log::info("ServicesCycle points (repeatable) awarded: {$rewardPoints} points to user ID {$customer->id} for completing cycle of {$completedCount} services.");
                            }
                        }
                    }
                }

            });

        } catch (\Throwable $e) {
            Log::error("Failed to process Loyalty Points on booking completion: " . $e->getMessage());
        }
    }

    /**
     * Allocate points when a review is successfully submitted.
     */
    public static function awardPointsForReview(Booking $booking): void
    {
        try {
            $customer = $booking->customer;
            if (!$customer) {
                return;
            }

            DB::transaction(function () use ($booking, $customer) {
                $alreadyAwardedReview = PointTransaction::where('user_id', $customer->id)
                    ->where('reference_type', Booking::class)
                    ->where('reference_id', $booking->id)
                    ->where('reason', 'review_submitted')
                    ->exists();

                if (!$alreadyAwardedReview) {
                    $reviewTask = RewardTasks::where('status', true)
                        ->where(function ($q) {
                            $q->where('trigger_event', 'review_submitted')
                              ->orWhere('action_link', 'rate_service');
                        })
                        ->first();

                    if ($reviewTask) {
                        $customer->pointTransactions()->create([
                            'type' => 'earn',
                            'amount' => $reviewTask->points,
                            'title_ar' => 'مكافأة تقييم حجز رقم: ' . $booking->booking_ref,
                            'title_en' => 'Booking Rating Reward: #' . $booking->booking_ref,
                            'reference_type' => Booking::class,
                            'reference_id' => $booking->id,
                            'reason' => 'review_submitted',
                        ]);

                        $customer->notify(new PointsNotification(
                            'نقاط مكافأة تقييم الخدمة! ⭐',
                            'شكراً لتقييمك! تم إيداع ' . $reviewTask->points . ' نقطة إضافية في محفظتك.',
                            $reviewTask->points,
                            'earn',
                            ['booking_ref' => $booking->booking_ref]
                        ));

                        Log::info("ReviewSubmission points awarded: {$reviewTask->points} points to user ID {$customer->id} for booking ID {$booking->id}.");
                    }
                }
            });

        } catch (\Throwable $e) {
            Log::error("Failed to process Review Submission Points: " . $e->getMessage());
        }
    }

    /**
     * Allocate points when a referred friend gets verified (approved) by the admin.
     */
    public static function awardPointsForReferralApproval(User $referredUser): void
    {
        try {
            if (empty($referredUser->referred_by_id)) {
                return;
            }

            $referrer = User::find($referredUser->referred_by_id);
            if (!$referrer) {
                return;
            }

            DB::transaction(function () use ($referredUser, $referrer) {
                $alreadyAwardedReferral = PointTransaction::where('user_id', $referrer->id)
                    ->where('reference_type', User::class)
                    ->where('reference_id', $referredUser->id)
                    ->where('reason', 'referral_approved')
                    ->exists();

                if (!$alreadyAwardedReferral) {
                    $referralTask = RewardTasks::where('status', true)
                        ->where('rule_category', 'referral')
                        ->first();

                    if ($referralTask) {
                        $referrer->pointTransactions()->create([
                            'type' => 'earn',
                            'amount' => $referralTask->points,
                            'title_ar' => 'مكافأة إحالة صديق: ' . $referredUser->full_name,
                            'title_en' => 'Friend Referral Approved: ' . $referredUser->full_name,
                            'reference_type' => User::class,
                            'reference_id' => $referredUser->id,
                            'reason' => 'referral_approved',
                        ]);

                        $referrer->notify(new PointsNotification(
                            'مكافأة دعوة صديق ناجحة! 🤝',
                            'حصلت على ' . $referralTask->points . ' نقطة بعد توثيق حساب صديقك ' . $referredUser->full_name . ' بنجاح.',
                            $referralTask->points,
                            'earn',
                            ['referred_friend_id' => $referredUser->id]
                        ));

                        Log::info("Referral points awarded: {$referralTask->points} points to referrer ID {$referrer->id} for friend ID {$referredUser->id} verification approval.");
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Error awarding referral points on approval: ' . $e->getMessage());
        }
    }
}
