<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GlobalLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = (string) Str::uuid();

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Log::error('GLOBAL REQUEST EXCEPTION', [
                'request_id'   => $requestId,
                'user'         => $this->userLabel($request),
                'url'          => $request->fullUrl(),
                'method'       => $request->method(),
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'request_data' => $this->sanitize($request),
                'error'        => $e->getMessage(),
            ]);

            throw $e;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('GLOBAL REQUEST', [
            'request_id'    => $requestId,
            'user'          => $this->userLabel($request),
            'url'           => $request->fullUrl(),
            'method'        => $request->method(),
            'ip'            => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'request_data'  => $this->sanitize($request),
            'status_code'   => $response->getStatusCode(),
            'response_data' => $this->sanitizeResponse(json_decode($response->getContent(), true)),
            'execution_ms'  => $executionTime,
        ]);

        return $response;
    }

    protected function userLabel(Request $request): string
    {
        $user = $request->user('user');

        if (! $user) {
            return 'guest';
        }

        return sprintf(
            '#%s %s %s <%s>',
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email
        );
    }

    protected function sanitize(Request $request): array
    {
        return $request->except([
            'password',
            'password_confirmation',
            'token',
            'access_token',
        ]);
    }

    protected function sanitizeResponse(?array $data): ?array
    {
        if (! $data) {
            return $data;
        }

        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
        ];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array($key, $sensitiveKeys, true)) {
                $value = '***REDACTED***';
            }
        });

        return $data;
    }
}
