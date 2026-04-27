<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Card\UserCardResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class UserCardController extends Controller
{
    use ApiResponseTrait;
    public function index()
    {
        $cards = auth()->user()->cards()->latest()->get();
        return $this->successResponse(UserCardResource::collection($cards), __('messages.cards_fetched_successfully'));
    }

    // حفظ بطاقة جديدة (بعد الحصول على التوكن من بوابة الدفع)
    public function store(Request $request)
    {
       
        // إذا كانت أول بطاقة، خليها الافتراضية
        $isFirstCard = auth()->user()->cards()->count() === 0;

        $card = auth()->user()->cards()->create([
            'card_token' => $request->card_token,
            'last_four' => $request->last_four,
            'card_brand' => $request->card_brand,
            'card_holder_name' => $request->card_holder_name,
            'is_default' => $isFirstCard,
        ]);

        return $this->successResponse(new UserCardResource($card), __('messages.card_added_successfully'), 201);
    }

    public function destroy($id)
    {
        $card = auth()->user()->cards()->findOrFail($id);
        $card->delete();
        return $this->successResponse(null, __('messages.card_deleted_successfully'));
    }
}
