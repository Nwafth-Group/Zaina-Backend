<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\SubmitVerifyAccountRequest;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KycController extends Controller
{
    use ApiResponseTrait;

    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function submit(SubmitVerifyAccountRequest $request)
    {
        $user = auth()->user();
        // 1. حماية: نمنع المستخدم من إرسال طلب جديد إذا كان طلبه قيد المراجعة أو تمت الموافقة عليه بالفعل
        // if (in_array($user->verification_status, ['pending', 'approved'])) {
        // return $this->successResponse(null,__('messages.kyc_already_submitted', ['status' => $user->verification_status]), 400);
        // }

        if($user->verification_status === 'pending') {
            return $this->successResponse(null,__('messages.kyc_already_submitted_pending'), 400);
        }
        if($user->verification_status === 'approved') {
            return $this->successResponse(null,__('messages.kyc_already_submitted_approved'), 400);
        }

        DB::beginTransaction();
        try {
            // 3. مسح الصور القديمة (لو كان مرفوض وبيرفع من جديد)
            if ($user->verification_status === 'rejected') {
                $oldMedia = $user->media()->whereIn('collection', ['kyc/id_front', 'kyc/id_back', 'kyc/selfie'])->get();
                foreach ($oldMedia as $media) {
                    $this->mediaService->delete($media);
                }
            }

            // 4. رفع الصور الجديدة باستخدام الـ MediaService
            $this->mediaService->upload($request->file('id_front'), $user, 'kyc/id_front', 'private');
            $this->mediaService->upload($request->file('id_back'), $user, 'kyc/id_back', 'private');
            $this->mediaService->upload($request->file('selfie'), $user, 'kyc/selfie', 'private');
            
            $updateData = [
                'verification_status' => 'pending',
                'rejection_reason'    => null
            ];

            if ($request->filled('referral_code') && empty($user->referred_by_id)) {
                $referralCodeRecord = \App\Models\UserReferralCode::where('code', $request->referral_code)->first();
                if ($referralCodeRecord && $referralCodeRecord->user_id !== $user->id) {
                    $updateData['referred_by_id'] = $referralCodeRecord->user_id;
                }
            }

            $user->update($updateData);

            DB::commit();

            return $this->successResponse([
                'verification_status' => $user->verification_status
            ], __('messages.kyc_submission_success'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء رفع المستندات.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



}


