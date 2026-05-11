<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{ 
    use ApiResponseTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. نتأكد أولاً إنه مسجل دخول (رغم إن Sanctum المفروض يتأكد قبلها)
        if (!auth()->check()) {
            return $this->errorResponse(__('messages.unauthenticated'), 401);
        }
    
        if(!auth()->user()->type || !in_array(auth()->user()->type, ['operation', 'super_admin'])) {
            return $this->errorResponse(__('messages.forbidden'), 403);
        }

        return $next($request);
    }
}
