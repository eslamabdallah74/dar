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
        $user = $request->user();
        $sessionLocale = $request->session()->get('locale');

        if ($user !== null && in_array($user->locale, ['ar', 'en'], true)) {
            App::setLocale($user->locale);
        } elseif (is_string($sessionLocale) && in_array($sessionLocale, ['ar', 'en'], true)) {
            App::setLocale($sessionLocale);
        } else {
            App::setLocale('ar');
        }

        return $next($request);
    }
}
