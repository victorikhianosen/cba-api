<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Journey\JourneyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Automatically records every authenticated admin request as a User Journey event —
// no business logic anywhere else needs to know this exists. Sits alongside
// GlobalLogger (which logs to the log file for debugging); this instead writes
// structured, queryable rows so an admin can replay a user's session.
class JourneyLogger
{
    // Auth endpoints already record their own login/logout journey events (with the
    // right event_type) via JourneyService — recording them again here would just
    // duplicate every session's start/end with a generic "action" entry.
    private const EXCLUDED_ROUTES = [
        'admin/auth/login',
        'admin/auth/2fa/verify',
        'admin/auth/logout',
        'admin/auth/session-timeout',
    ];

    // Same request repeated (double-click, polling, optimistic retries) within this
    // window is collapsed into a single journey entry instead of one per hit.
    private const DEDUPE_SECONDS = 3;

    public function __construct(private JourneyService $journeys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->record($request, $response);

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        $user = $request->user('user');

        if (! $user instanceof User) {
            return;
        }

        $path = trim($request->path(), '/');

        if (in_array($path, self::EXCLUDED_ROUTES, true)) {
            return;
        }

        $sessionId = (string) $user->currentAccessToken()?->getKey();

        if ($sessionId === '') {
            return;
        }

        if ($this->isDuplicate($sessionId, $request)) {
            return;
        }

        $statusCode = $response instanceof StreamedResponse ? 200 : $response->getStatusCode();

        $responseBody = $response instanceof StreamedResponse
            ? null
            : json_decode((string) $response->getContent(), true);

        $this->journeys->recordFromRequest($request, $user, $sessionId, $statusCode, is_array($responseBody) ? $responseBody : null);
    }

    private function isDuplicate(string $sessionId, Request $request): bool
    {
        $key = sprintf('journey_dedupe:%s:%s:%s', $sessionId, $request->method(), $request->path());

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, self::DEDUPE_SECONDS);

        return false;
    }
}
