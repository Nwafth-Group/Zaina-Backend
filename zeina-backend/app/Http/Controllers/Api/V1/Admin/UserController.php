<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\Media;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $verification_status = $request->input('verification_status');

        // Compute verification breakdown counts before filtering by verification status
        $countsQuery = User::query();
        if ($request->filled('types')) {
            $types = is_array($request->input('types')) ? $request->input('types') : explode(',', $request->input('types'));
            $countsQuery->whereIn('type', array_filter($types));
        } elseif ($request->has('type')) {
            $countsQuery->where('type', $request->input('type'));
        }
        if ($request->filled('search')) {
            $this->applySearchFilter($countsQuery, $request->input('search'));
        }

        $counts = [
            'all' => (clone $countsQuery)->count(),
            'pending' => (clone $countsQuery)->where('verification_status', 'pending')->count(),
            'approved' => (clone $countsQuery)->where('verification_status', 'approved')->count(),
            'rejected' => (clone $countsQuery)->where('verification_status', 'rejected')->count(),
            'unverified' => (clone $countsQuery)->where(function ($q) {
                $q->whereNull('verification_status')->orWhere('verification_status', 'unverified')->orWhere('verification_status', '');
            })->count(),
        ];

        if (in_array($verification_status, ['pending', 'rejected'])) {
            $query = User::with(['roles', 'media']);
        } else {
            $query = User::with('roles');
        }

        // 1. الفلترة بحالة التوثيق (الـ New Workflow)
        if ($verification_status) {
            $query->where('verification_status', $verification_status);
        }

        // 2. الفلترة بحالة الحساب العامة (active, inactive)
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // 3. الفلترة بنوع المستخدم — يدعم نوعاً واحداً (type) أو عدة أنواع (types)
        // لفصل العملاء عن حسابات الإدارة في الواجهة.
        if ($request->filled('types')) {
            $types = is_array($request->input('types'))
                ? $request->input('types')
                : explode(',', $request->input('types'));
            $query->whereIn('type', array_filter($types));
        } elseif ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // 4. الفلترة بالـ Role (الصلاحيات الإدارية)
        if ($request->has('role')) {
            $role = $request->input('role');
            $query->whereHas('roles', function ($r) use ($role) {
                $r->where('name', $role);
            });
        }

        // 5. الفلترة بالفترة الزمنية (تاريخ التسجيل)
        if ($request->input('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->input('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Filter by location (service zone of default customer addresses)
        if ($request->filled('service_zone_id')) {
            $query->whereHas('addresses', function ($aQuery) use ($request) {
                $aQuery->where('is_active', true)
                    ->where('service_zone_id', $request->input('service_zone_id'));
            });
        }

        // 6. تفعيل الـ Search المتقدم للنصوص
        if ($request->filled('search')) {
            $this->applySearchFilter($query, $request->input('search'));
        }

        // 7. الترتيب الديناميكي الآمن (Sorting)
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'status', 'verification_status', 'type', 'id', 'first_name'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest(); // Fallback الافتراضي
        }

        // 8. التقسيم (Pagination)
        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);

        return $this->successResponse([
            'items' => UserResource::collection($users),
            'counts' => $counts,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], __('messages.users_retrieved'));
    }

    public function show(User $user)
    {
        $user->load([
            'roles', 
            'referredBy',
            'addresses',
            'bookings.service',
            'bookings.workers',
            'reviews.booking',
            'reviews.reviewable',
            'pointTransactions' => function($q) {
                $q->latest()->limit(50);
            }
        ]);

        return $this->successResponse([
            'user' => new UserResource($user),
            'addresses' => $user->addresses,
            'bookings' => $user->bookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'booking_ref' => $booking->booking_ref,
                    'service_name' => $booking->service ? (app()->getLocale() == 'ar' ? $booking->service->name_ar : $booking->service->name_en) : '-',
                    'worker_name' => $booking->workers->isNotEmpty() ? $booking->workers->pluck('name')->implode(', ') : '-',
                    'booking_date' => $booking->booking_date,
                    'status' => $booking->status,
                    'total_amount' => $booking->total_amount,
                ];
            }),
            'reviews' => $user->reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'booking_id' => $review->booking_id,
                    'booking_ref' => $review->booking ? $review->booking->booking_ref : '-',
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at?->toDateTimeString(),
                    'reviewable_name' => $review->reviewable ? ($review->reviewable->name ?? $review->reviewable->full_name ?? $review->reviewable->name_en ?? '-') : '-',
                ];
            }),
            'transactions' => $user->pointTransactions->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => $tx->amount,
                    'reason' => $tx->reason,
                    'title' => app()->getLocale() == 'ar' ? $tx->title_ar : $tx->title_en,
                    'created_at' => $tx->created_at?->toDateTimeString(),
                ];
            }),
        ], 'User details retrieved successfully.');
    }

    public function verifyUser(Request $request, User $user)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string|max:500|nullable',
        ], [
            'action.required' => __('messages.action_required'),
            'rejection_reason.required_if' => __('messages.rejection_reason_required'),
        ]);

        if ($request->action === 'approve') {
            $user->verification_status = 'approved';
            $user->rejection_reason = null;
            $user->save();

            // Award points to the referrer on approval
            try {
                \App\Service\LoyaltyPointService::awardPointsForReferralApproval($user);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to award referral points on KYC approval to {$user->id}: " . $e->getMessage());
            }

            // 1. Send SMS
            try {
                $displayName = $user->first_name ?? $user->name ?? '';
                $smsMessage = __('sms.kyc_approved_sms', ['name' => $displayName]);
                app(\App\Service\SmsService::class)->sendSms($user->phone_number, $smsMessage);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send KYC approval SMS to {$user->id}: " . $e->getMessage());
            }

            // 2. Send Email
            try {
                if (!empty($user->email)) {
                    $companyInfo = \App\Models\CompanyInfo::with('media')->first();
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\KycApprovedMail($user, $companyInfo));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send KYC approval email to {$user->id}: " . $e->getMessage());
            }

            // 3. Send Push & Database Notification
            try {
                $user->notify(new \App\Notifications\KycStatusNotification('approved'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send KYC approval notification to {$user->id}: " . $e->getMessage());
            }
        } else {
            $user->verification_status = 'rejected';
            $user->rejection_reason = $request->rejection_reason;
            $user->save();

            // 1. Send SMS
            try {
                $displayName = $user->first_name ?? $user->name ?? '';
                $smsMessage = __('sms.kyc_rejected_sms', ['name' => $displayName, 'reason' => $request->rejection_reason]);
                app(\App\Service\SmsService::class)->sendSms($user->phone_number, $smsMessage);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send KYC rejection SMS to {$user->id}: " . $e->getMessage());
            }

            // 2. Send Email
            try {
                if (!empty($user->email)) {
                    $companyInfo = \App\Models\CompanyInfo::with('media')->first();
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\KycRejectedMail($user, $request->rejection_reason, $companyInfo));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send KYC rejection email to {$user->id}: " . $e->getMessage());
            }

            // 3. Send Push & Database Notification
            try {
                $user->notify(new \App\Notifications\KycStatusNotification('rejected', $request->rejection_reason));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send KYC rejection notification to {$user->id}: " . $e->getMessage());
            }
        }

        return $this->successResponse(null,
            $request->action === 'approve' ? __('messages.user_approved_success') : __('messages.user_rejected_success')
        );
    }

    private function applySearchFilter($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('id_number', $search)
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%");
        });
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        // 1. تحديث الحالة (Active/Inactive)
        if ($request->has('status')) {
            $user->status = $request->status;
        }

        // 2. تحديث الأدوار (Assign Roles)
        if ($request->has('roles')) {
            $user->syncRoles($request->roles); // تمسح القديم وبتضيف جديد
        }
        $user->save();

        return $this->successResponse(new UserResource($user->load('roles')), __('messages.user_updated'));
    }

    /**
     * تغيير حالة المستخدم بسرعة (Toggle Status)
     */
    public function toggleStatus(User $user)
    {
        // منع المدير من تعطيل حسابه الشخصي بالخطأ (Safety Check)
        if ($user->id === auth()->id()) {
            return $this->errorResponse(__('messages.cannot_toggle_own_status'), 403);
        }

        $user->status = ($user->status === 'active') ? 'inactive' : 'active';
        $user->save();

        return $this->successResponse(null, __('messages.user_status_toggled', ['status' => $user->status]));
    }

    /**
     * إعادة تعيين كلمة المرور من قبل المسؤول
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user)
    {
        $data = $request->validated();

        $user->password = Hash::make($data['password']);
        $user->save();

        return $this->successResponse(null, __('messages.password_reset_success'));
    }

    public function downloadKycDoc(Request $request, $mediaId)
    {
        // 1. تأمين الإجراء: الأدمن فقط من يستطيع العبور
        // abort_if(!auth()->user()->tokenCan('admin'), 403);
        $mediaItem = Media::findOrFail($mediaId);

        // 2. التأكد من أن الملف ينتمي لديسك البرايفيت
        if ($mediaItem->disk !== 'private') {
            abort(404, 'الملف غير موجود في النطاق المحمي.');
        }

        // 3. قراءة الملف وتمريره كـ Stream آمن للمتصفح دون كشف مساره الفيزيائي
        if (! Storage::disk('private')->exists($mediaItem->path)) {
            abort(404, 'الملف الفيزيائي غير موجود على السيرفر.');
        }

        return Storage::disk('private')->response($mediaItem->path, $mediaItem->file_name);
    }
}
