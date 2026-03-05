<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
   public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Accept-Language', '');
        $queryLang = (string) $request->query('lang', '');
        $locale = '';
        if ($header !== '') {
            $first = strtolower(trim(explode(',', $header)[0] ?? ''));
            $locale = explode('-', $first)[0] ?? '';
        }
        if ($locale === '') {
            $locale = strtolower($queryLang);
        }
        App::setLocale(in_array($locale, ['en', 'ar'], true) ? $locale : 'en');
        return $next($request);
    }
}
