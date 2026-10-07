<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueryTokenToAuthHeader
{
    /**
     * If an authentication token is provided in the query string (e.g. for EventSource / SSE),
     * set it into the Authorization header so Sanctum can authenticate it seamlessly.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('token') && ! $request->headers->has('Authorization')) {
            $token = (string) $request->query('token');
            if ($token !== '') {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
