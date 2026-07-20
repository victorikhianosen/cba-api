<?php

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

            Log::error('');
            Log::error('************* EXCEPTION *****************');
            Log::error('REQUEST ID: ' . $requestId);
            Log::error('USER: ' . $this->userLabel($request));
            Log::error('URL: ' . $request->fullUrl());
            Log::error('METHOD: ' . $request->method());
            Log::error('IP: ' . $request->ip());
            Log::error('USER AGENT: ' . $request->userAgent());
            Log::error('REQUEST DATA: ' . json_encode($this->sanitize($request)));
            Log::error('ERROR MESSAGE: ' . $e->getMessage());
            Log::error('****************************************');
            Log::error('');

            throw $e;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('');
        Log::info('************* GLOBAL REQUEST *************');
        Log::info('REQUEST ID: ' . $requestId);
        Log::info('USER: ' . $this->userLabel($request));
        Log::info('URL: ' . $request->fullUrl());
        Log::info('METHOD: ' . $request->method());
        Log::info('IP: ' . $request->ip());
        Log::info('USER AGENT: ' . $request->userAgent());
        Log::info('REQUEST DATA: ' . json_encode($this->sanitize($request), JSON_PRETTY_PRINT));
        Log::info('STATUS CODE: ' . $response->getStatusCode());
        Log::info('RESPONSE DATA: ' . json_encode(
            $this->sanitizeResponse(json_decode($response->getContent(), true)),
            JSON_PRETTY_PRINT
        ));
        Log::info('Execution Time: ' . $executionTime . ' ms');
        Log::info('*******************************************');
        Log::info('');

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