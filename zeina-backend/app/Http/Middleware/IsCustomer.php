<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsCustomer
{
    use ApiResponseTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    { 
        $user = auth()->user();

        if (!$user || $user->type !== 'customer') {
             return $this->errorResponse(__('messages.forbidden'), 403);
        }
        if ($user->status !== 'active') {
            $user->tokens()->delete();
            $user->devices()->delete();
            return $this->errorResponse(__('messages.account_inactive'), 403);
        }
        if($user->verification_status !== 'approved') {
            return $this->errorResponse(__('messages.kyc_not_verified'), 403);
        }
        return $next($request);
    }
}
