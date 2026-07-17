<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissionMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user('user');

        if (! $user) {
            return $this->error(
                message: 'Unauthenticated.',
                responseCode: '401',
                statusCode: 401,
            );
        }

        if (! $user->can($permission)) {
            return $this->error(
                message: 'You do not have permission to perform this action ' . str_replace('_', ' ', $permission) . '.',
                responseCode: '403',
                statusCode: 403,
            );
        }

        return $next($request);
    }
}
