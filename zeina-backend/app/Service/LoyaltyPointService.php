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
                // Find all active booking completed rules
                $bookingRules = RewardTasks::where('status', true)
                    ->where('rule_category', 'booking')
                    ->get();

                if ($bookingRules->isEmpty()) {
                    return;
                }

                $completedCount = Booking::where('customer_id', $customer->id)
                    ->where('status', 'completed')
                    ->count();

                foreach ($bookingRules as $rule) {
                    $requiredCount = (int) ($rule->required_booking_count ?? 1);
                    if ($requiredCount < 1) {
                        $requiredCount = 1;
                    }

                    if ($rule->frequency_type === 'one_time') {
                        if ($completedCount >= $requiredCount) {
                            $alreadyAwarded = PointTransaction::where('user_id', $customer->id)
                                ->where('reason', 'booking_completed_rule_' . $rule->id)
                                ->exists();

                            if (!$alreadyAwarded) {
                                $customer->pointTransactions()->create([
                                    'type' => 'earn',
                                    'amount' => $rule->points,
                                    'title_ar' => 'مكافأة إتمام الخدمة: ' . ($rule->title_ar ?? 'إتمام الحجز'),
                                    'title_en' => 'Service Completion Reward: ' . ($rule->title_en ?? 'Completed Booking'),
                                    'reference_type' => Booking::class,
                                    'reference_id' => $booking->id,
                                    'reason' => 'booking_completed_rule_' . $rule->id,
                                ]);

                                $customer->notify(new PointsNotification(
                                    'حصلت على نقاط جديدة! 🎉',
                                    'تمت إضافة ' . $rule->points . ' نقطة إلى رصيدك لإتمامك الخدمة بنجاح.',
                                    $rule->points,
                                    'earn',
                                    ['booking_ref' => $booking->booking_ref]
                                ));

                                Log::info("BookingCompleted points (one_time) awarded: {$rule->points} points to user ID {$customer->id} for rule ID {$rule->id} on booking ID {$booking->id}.");
                            }
                        }
                    } else {
                        // repeatable cycle
                        if ($completedCount > 0 && $completedCount % $requiredCount === 0) {
                            $alreadyAwarded = PointTransaction::where('user_id', $customer->id)
                                ->where('reason', 'booking_completed_rule_' . $rule->id . '_cycle_' . $completedCount)
                                ->exists();

                            if (!$alreadyAwarded) {
                                $customer->pointTransactions()->create([
                                    'type' => 'earn',
                                    'amount' => $rule->points,
                                    'title_ar' => 'مكافأة إكمال دورة الخدمات: ' . ($rule->title_ar ?? 'إتمام الحجز'),
                                    'title_en' => 'Services Cycle Completion Reward: ' . ($rule->title_en ?? 'Completed Booking'),
                                    'reference_type' => Booking::class,
                                    'reference_id' => $booking->id,
                                    'reason' => 'booking_completed_rule_' . $rule->id . '_cycle_' . $completedCount,
                                ]);

                                $customer->notify(new PointsNotification(
                                    'حصلت على نقاط جديدة! 🎉',
                                    'تمت إضافة ' . $rule->points . ' نقطة إلى رصيدك لإتمامك الخدمة بنجاح.',
                                    $rule->points,
                                    'earn',
                                    ['booking_ref' => $booking->booking_ref]
                                ));

                                Log::info("BookingCompleted points (repeatable) awarded: {$rule->points} points to user ID {$customer->id} for rule ID {$rule->id} cycle {$completedCount} on booking ID {$booking->id}.");
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
                        ->where('action_type', 'rate_service')
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
