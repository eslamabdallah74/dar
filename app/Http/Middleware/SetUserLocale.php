<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionLocale = $request->session()->get('locale');

        if (is_string($sessionLocale) && in_array($sessionLocale, ['ar', 'en'], true)) {
            App::setLocale($sessionLocale);
        } else {
            $defaultLocale = config('app.locale');
            App::setLocale(is_string($defaultLocale) && in_array($defaultLocale, ['ar', 'en'], true) ? $defaultLocale : 'ar');
        }

        return $next($request);
    }
}
