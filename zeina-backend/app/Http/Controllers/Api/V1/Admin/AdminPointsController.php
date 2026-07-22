<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointSetting;
use App\Models\PointTransaction;
use App\Models\RewardTasks;
use App\Models\Service;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPointsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of loyalty points earning rules.
     */
    public function getRules(Request $request)
    {
        $query = RewardTasks::with('service');

        if ($request->filled('category')) {
            $query->where('rule_category', $request->category);
        }

        if ($request->filled('search')) {
            $searchTerm = '%'.$request->search.'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title_ar', 'like', $searchTerm)
                    ->orWhere('title_en', 'like', $searchTerm);
            });
        }

        return $this->successResponse($query->latest()->get(), 'Loyalty points rules retrieved successfully.');
    }

    /**
     * Store a new points earning rule.
     */
    public function storeRule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'subtitle_ar' => 'nullable|string|max:255',
            'subtitle_en' => 'nullable|string|max:255',
            'points' => 'required|integer|min:1',
            'rule_category' => 'required|string|in:social_media,referral,booking,service',
            'service_id' => 'nullable|integer|exists:services,id',
            'status' => 'required|boolean',
            'action_type' => 'nullable|string|in:url,invite_friend,rate_service',
            'action_link' => 'nullable|string',
            'required_booking_count' => 'nullable|integer|min:1',
            'frequency_type' => 'required|string|in:one_time,repeatable',
            'eligible_services' => 'nullable|array',
            'eligible_services.*' => 'integer|exists:services,id',
            'calculation_method' => 'required|string|in:fixed,price_based',
            'points_expiration_months' => 'nullable|integer|min:1',
            'action_text_ar' => 'nullable|string|max:255',
            'action_text_en' => 'nullable|string|max:255',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->rule_category === 'social_media') {
                if ($request->frequency_type !== 'one_time') {
                    $validator->errors()->add('frequency_type', 'Social media rules must have a "one_time" frequency.');
                }
                if ($request->action_type !== 'url') {
                    $validator->errors()->add('action_type', 'Social media rules action type must always be "url" (open link).');
                }
            }
            if ($request->rule_category === 'referral') {
                if ($request->frequency_type !== 'repeatable') {
                    $validator->errors()->add('frequency_type', 'Referral rules must have a "repeatable" frequency.');
                }
                $referralExists = RewardTasks::where('rule_category', 'referral')->exists();
                if ($referralExists) {
                    $validator->errors()->add('rule_category', 'Only one referral rule is allowed.');
                }
            }
            if ($request->rule_category === 'service') {
                if (empty($request->service_id)) {
                    $validator->errors()->add('service_id', 'Please select a service for this service rule.');
                }
                if ($request->frequency_type !== 'repeatable') {
                    $validator->errors()->add('frequency_type', 'Specific service rules must have a "repeatable" frequency.');
                }
                if ($request->service_id) {
                    $serviceRuleExists = RewardTasks::where('rule_category', 'service')
                        ->where('service_id', $request->service_id)
                        ->exists();
                    if ($serviceRuleExists) {
                        $validator->errors()->add('service_id', 'A loyalty rule for this service already exists. Please choose a different service.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        // Custom validation check: rule name must be unique within same category
        $exists = RewardTasks::where('rule_category', $request->rule_category)
            ->where(function ($q) use ($request) {
                $q->where('title_ar', $request->title_ar)
                    ->orWhere('title_en', $request->title_en);
            })->exists();

        if ($exists) {
            return $this->errorResponse('A rule with this name already exists in this category.', 422);
        }

        $data = $request->all();
        if (empty($data['action_text_ar'])) {
            $data['action_text_ar'] = $request->title_ar;
        }
        if (empty($data['action_text_en'])) {
            $data['action_text_en'] = $request->title_en;
        }
        if (empty($data['action_type'])) {
            $data['action_type'] = 'url';
        }

        $rule = RewardTasks::create($data);

        return $this->successResponse($rule->load('service'), 'Earning rule created successfully.', 201);
    }

    /**
     * Update an earning rule.
     */
    public function updateRule(Request $request, $id)
    {
        $rule = RewardTasks::find($id);
        if (!$rule) {
            return $this->errorResponse('Rule not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'subtitle_ar' => 'nullable|string|max:255',
            'subtitle_en' => 'nullable|string|max:255',
            'points' => 'required|integer|min:1',
            'rule_category' => 'required|string|in:social_media,referral,booking,service',
            'service_id' => 'nullable|integer|exists:services,id',
            'status' => 'required|boolean',
            'action_type' => 'nullable|string|in:url,invite_friend,rate_service',
            'action_link' => 'nullable|string',
            'required_booking_count' => 'nullable|integer|min:1',
            'frequency_type' => 'required|string|in:one_time,repeatable',
            'eligible_services' => 'nullable|array',
            'eligible_services.*' => 'integer|exists:services,id',
            'calculation_method' => 'required|string|in:fixed,price_based',
            'points_expiration_months' => 'nullable|integer|min:1',
            'action_text_ar' => 'nullable|string|max:255',
            'action_text_en' => 'nullable|string|max:255',
        ]);

        $validator->after(function ($validator) use ($request, $id) {
            if ($request->rule_category === 'social_media') {
                if ($request->frequency_type !== 'one_time') {
                    $validator->errors()->add('frequency_type', 'Social media rules must have a "one_time" frequency.');
                }
                if ($request->action_type !== 'url') {
                    $validator->errors()->add('action_type', 'Social media rules action type must always be "url" (open link).');
                }
            }
            if ($request->rule_category === 'referral') {
                if ($request->frequency_type !== 'repeatable') {
                    $validator->errors()->add('frequency_type', 'Referral rules must have a "repeatable" frequency.');
                }
                $referralExists = RewardTasks::where('rule_category', 'referral')->where('id', '!=', $id)->exists();
                if ($referralExists) {
                    $validator->errors()->add('rule_category', 'Only one referral rule is allowed.');
                }
            }
            if ($request->rule_category === 'service') {
                if (empty($request->service_id)) {
                    $validator->errors()->add('service_id', 'Please select a service for this service rule.');
                }
                if ($request->frequency_type !== 'repeatable') {
                    $validator->errors()->add('frequency_type', 'Specific service rules must have a "repeatable" frequency.');
                }
                if ($request->service_id) {
                    $serviceRuleExists = RewardTasks::where('rule_category', 'service')
                        ->where('service_id', $request->service_id)
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($serviceRuleExists) {
                        $validator->errors()->add('service_id', 'A loyalty rule for this service already exists. Please choose a different service.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        // Unique rule name in category check excluding self
        $exists = RewardTasks::where('rule_category', $request->rule_category)
            ->where('id', '!=', $id)
            ->where(function ($q) use ($request) {
                $q->where('title_ar', $request->title_ar)
                    ->orWhere('title_en', $request->title_en);
            })->exists();

        if ($exists) {
            return $this->errorResponse('A rule with this name already exists in this category.', 422);
        }

        $data = $request->all();
        if (empty($data['action_text_ar'])) {
            $data['action_text_ar'] = $request->title_ar;
        }
        if (empty($data['action_text_en'])) {
            $data['action_text_en'] = $request->title_en;
        }
        if (empty($data['action_type'])) {
            $data['action_type'] = 'url';
        }

        $rule->update($data);

        return $this->successResponse($rule, 'Earning rule updated successfully.');
    }

    /**
     * Delete an earning rule.
     */
    public function destroyRule($id)
    {
        $rule = RewardTasks::find($id);
        if (! $rule) {
            return $this->errorResponse('Rule not found.', 404);
        }

        if ($rule->rule_category === 'referral') {
            return $this->errorResponse('Referral category rule cannot be deleted.', 422);
        }

        $rule->delete();

        return $this->successResponse(null, 'Earning rule deleted successfully.');
    }

    /**
     * List all services points configurations.
     */
    public function getServices()
    {
        $services = Service::select(
            'id', 'name_ar', 'name_en', 'service_code', 'base_price',
            'points_redemption_enabled', 'points_redemption_method',
            'points_required_fixed', 'points_conversion_rate'
        )->latest()->get();

        return $this->successResponse($services, 'Services points configuration retrieved successfully.');
    }

    /**
     * Update service-specific points configuration.
     */
    public function updateServicePoints(Request $request, $id)
    {
        $service = Service::find($id);
        if (! $service) {
            return $this->errorResponse('Service not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'points_redemption_enabled' => 'required|boolean',
            'points_redemption_method' => 'required|string|in:fixed,price_based',
            'points_required_fixed' => 'required|integer|min:0',
            'points_conversion_rate' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $service->update([
            'points_redemption_enabled' => $request->points_redemption_enabled,
            'points_redemption_method' => $request->points_redemption_method,
            'points_required_fixed' => $request->points_required_fixed,
            'points_conversion_rate' => $request->points_conversion_rate,
        ]);

        return $this->successResponse($service, 'Service points configuration updated successfully.');
    }

    /**
     * Retrieve list of points transactions.
     */
    public function getTransactions(Request $request)
    {
        $query = PointTransaction::with('user');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone_number', 'like', $search);
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $paginator = $query->latest()->paginate(15);

        $formattedData = [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];

        return $this->successResponse($formattedData, 'Point transactions retrieved successfully.');
    }

    /**
     * Get logged-in user's monthly points quota summary.
     */
    public function myQuota(Request $request)
    {
        $user = auth()->user();
        $limit = $user->monthly_points_limit !== null ? (int) $user->monthly_points_limit : null;

        $usedThisMonth = 0;
        if ($limit !== null && $limit > 0) {
            $usedThisMonth = (int) PointTransaction::where('type', 'adjustment')
                ->where('amount', '>', 0)
                ->where('created_by_user_id', $user->id)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
        }

        $remaining = $limit !== null ? max(0, $limit - $usedThisMonth) : null;

        return $this->successResponse([
            'monthly_limit' => $limit,
            'used_this_month' => $usedThisMonth,
            'remaining_quota' => $remaining,
        ], 'Monthly points quota retrieved successfully.');
    }

    /**
     * Add or deduct points manually for a specific user.
     */
    public function manualAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'action' => 'required|string|in:credit,debit',
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::find($request->user_id);
        $amount = (int) $request->amount;
        $balance = PointTransaction::getUserPointsBalance($user->id);
        $admin = auth()->user();

        if ($request->action === 'debit') {
            $isAr = app()->getLocale() === 'ar';
            if ($amount > $balance) {
                return $this->errorResponse(
                    $isAr
                        ? "رصيد النقاط غير كافٍ. العميل يملك فقط {$balance} نقطة."
                        : "Insufficient points balance. Customer only has {$balance} points.",
                    422
                );
            }

            $newBalance = $balance - $amount;
            $transaction = PointTransaction::create([
                'user_id' => $user->id,
                'type' => 'adjustment',
                'amount' => -$amount,
                'points_before' => $balance,
                'points_after' => $newBalance,
                'title_ar' => 'تعديل نقاط يدوي (خصم)',
                'title_en' => 'Manual Points Adjustment (Debit)',
                'reason' => $request->reason,
                'created_by' => $admin?->full_name ?? 'Admin',
                'created_by_user_id' => $admin?->id,
            ]);
        } else {
            $isAr = app()->getLocale() === 'ar';
            // Check employee monthly points limit if configured
            if ($admin && $admin->monthly_points_limit !== null && $admin->monthly_points_limit > 0) {
                $usedThisMonth = (int) PointTransaction::where('type', 'adjustment')
                    ->where('amount', '>', 0)
                    ->where('created_by_user_id', $admin->id)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('amount');

                $remainingQuota = max(0, (int) $admin->monthly_points_limit - $usedThisMonth);

                if ($amount > $remainingQuota) {
                    return $this->errorResponse(
                        $isAr
                            ? "لقد تجاوزت الحد المسموح لك لمنح النقاط هذا الشهر (المتبقي لديك: {$remainingQuota} نقطة من أصل {$admin->monthly_points_limit})."
                            : "You have exceeded your monthly points grant limit (remaining: {$remainingQuota} of {$admin->monthly_points_limit} pts).",
                        422
                    );
                }
            }

            $newBalance = $balance + $amount;
            $transaction = PointTransaction::create([
                'user_id' => $user->id,
                'type' => 'adjustment',
                'amount' => $amount,
                'points_before' => $balance,
                'points_after' => $newBalance,
                'title_ar' => 'تعديل نقاط يدوي (إضافة)',
                'title_en' => 'Manual Points Adjustment (Credit)',
                'reason' => $request->reason,
                'created_by' => $admin?->full_name ?? 'Admin',
                'created_by_user_id' => $admin?->id,
            ]);
        }

        return $this->successResponse([
            'transaction' => $transaction,
            'new_balance' => $newBalance,
        ], 'Manual points adjustment recorded successfully.');
    }
}
