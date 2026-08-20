<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::guard('api')->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
