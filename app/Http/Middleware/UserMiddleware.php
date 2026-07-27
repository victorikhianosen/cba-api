<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Sanctum resolves a token via its polymorphic "tokenable" relation, not by guard name — without
// this check, a customer's token would still authenticate on a route protected only by "auth:user",
// since the "user" guard's provider is never consulted to verify the token's actual owner type.
class UserMiddleware
{
    use ApiResponse;

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user');

        if (! $user instanceof User) {
            return $this->error(
                message: 'Unauthenticated User.',
                responseCode: '401',
                statusCode: 401,
            );
        }

        return $next($request);
    }
}
