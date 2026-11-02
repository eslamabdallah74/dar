<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403, 'غير مصرح لك بالوصول إلى لوحة الإدارة.');
            }

            return redirect()->route('dashboard')->with('error', 'غير مصرح لك بالوصول إلى لوحة الإدارة.');
        }

        return $next($request);
    }
}
