<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Mirror of UserMiddleware for the customer-facing side — stops a staff user's token from
// authenticating on customer routes, once those routes exist.
class CustomerMiddleware
{
    use ApiResponse;

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            return $this->error(
                message: 'Unauthenticated Customer.',
                responseCode: '401',
                statusCode: 401,
            );
        }

        return $next($request);
    }
}
