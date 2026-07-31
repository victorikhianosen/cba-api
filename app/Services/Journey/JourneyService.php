<?php

declare(strict_types=1);

namespace App\Services\Journey;

use App\Models\User;
use App\Models\UserJourney;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

// Writes to user_journeys — the counterpart to AuditService, but scoped to
// reconstructing a single session's chronological timeline (login through
// logout/timeout) rather than auditing individual data changes.
class JourneyService
{
    // Path segments that map straight to a specific action verb, checked in order —
    // first match wins. Keeps the inference data-driven instead of a long if/else chain.
    private const ACTION_KEYWORDS = [
        'approve'  => 'approve',
        'reject'   => 'reject',
        'reverse'  => 'reverse',
        'export'   => 'export',
        'download' => 'download',
        'upload'   => 'upload',
        'print'    => 'print',
        'search'   => 'search',
        'filter'   => 'filter',
        'lookup'   => 'search',
        'login'    => 'login',
        'logout'   => 'logout',
    ];

    private const METHOD_ACTIONS = [
        'GET'    => 'view',
        'POST'   => 'create',
        'PUT'    => 'update',
        'PATCH'  => 'update',
        'DELETE' => 'delete',
    ];

    // Fields worth pulling out of a request/response body to identify *which* record
    // was touched — a customer's CIF number, a posting's reference, a plain id, etc.
    // Checked in this order so the most human-readable identifier wins when several exist.
    private const IDENTIFIER_KEYS = [
        'cif_number', 'reference', 'reference_number', 'account_number',
        'code', 'username', 'email', 'title', 'name', 'business_name', 'id',
    ];

    // Never persisted, whichever side of the request/response they show up on.
    // (The 2FA "code" field never reaches here — /auth/2fa/verify is excluded entirely.)
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'pin', 'panic_password',
        'token', 'access_token', 'two_factor_token',
    ];

    public function recordLogin(User $user, string $sessionId): UserJourney
    {
        return $this->record(
            user: $user,
            sessionId: $sessionId,
            eventType: 'login',
            action: 'login',
            module: 'auth',
            description: "User '{$user->username}' ({$user->code}) logged in.",
        );
    }

    public function recordLogout(User $user, string $sessionId, string $eventType = 'logout'): UserJourney
    {
        return $this->record(
            user: $user,
            sessionId: $sessionId,
            eventType: $eventType,
            action: 'logout',
            module: 'auth',
            description: $eventType === 'session_timeout'
                ? "User '{$user->username}' ({$user->code}) was logged out after the session timed out."
                : "User '{$user->username}' ({$user->code}) logged out.",
        );
    }

    // Called from JourneyLogger middleware for every other authenticated request —
    // infers everything from the request/route/response itself so call sites elsewhere
    // in the app never need to know about journeys. $responseBody is the already
    // JSON-decoded response (null for streamed/binary responses, e.g. file exports).
    public function recordFromRequest(
        Request $request,
        User $user,
        string $sessionId,
        int $statusCode,
        ?array $responseBody = null,
    ): UserJourney {
        $method = $request->method();
        $path   = trim((string) $request->path(), '/');
        $segments = array_values(array_filter(explode('/', $path)));

        // Path looks like "admin/{module}/...", "admin" is always the prefix.
        $module = $segments[1] ?? null;

        $action = $this->inferAction($segments, $method);
        $eventType = $method === 'GET' ? 'page_view' : 'action';

        $routeParams = $request->route()?->parameters() ?: null;

        // The response (create/update) is generally the more trustworthy source of
        // truth for "which record" — it's the record as it actually ended up, not
        // just what was submitted (e.g. a server-generated cif_number/reference).
        // The submitted input still fills in gaps the response doesn't echo back
        // (e.g. a DELETE's route id, or fields the resource doesn't expose).
        $responseIdentifiers = $this->extractIdentifiers($responseBody['data'] ?? null);
        $requestIdentifiers  = $method !== 'GET' ? $this->extractIdentifiers($request->except(self::REDACTED_KEYS)) : [];
        $identifiers = array_merge($routeParams ?? [], $requestIdentifiers, $responseIdentifiers);

        $input = $method !== 'GET'
            ? $this->sanitize($request->except(self::REDACTED_KEYS))
            : null;

        return $this->record(
            user: $user,
            sessionId: $sessionId,
            eventType: $eventType,
            action: $action,
            module: $module,
            method: $method,
            route: $path,
            url: $request->fullUrl(),
            description: $this->describe($action, $module, $identifiers),
            metadata: array_filter([
                'status_code'  => $statusCode,
                'identifiers'  => $identifiers ?: null,
                'route_params' => $routeParams,
                'query'        => $request->query() ?: null,
                'input'        => $input ?: null,
                'message'      => is_string($responseBody['message'] ?? null) ? $responseBody['message'] : null,
            ], fn ($value) => $value !== null),
        );
    }

    private function record(
        User $user,
        string $sessionId,
        string $eventType,
        ?string $action = null,
        ?string $module = null,
        ?string $method = null,
        ?string $route = null,
        ?string $url = null,
        ?string $description = null,
        ?array $metadata = null,
    ): UserJourney {
        return UserJourney::create([
            'performed_by_type' => 'User',
            'performed_by_id'   => $user->id,
            'performed_by_name' => $user->username,

            'session_id' => $sessionId,
            'event_type' => $eventType,
            'action'     => $action,
            'module'     => $module,
            'method'     => $method,
            'route'      => $route,
            'url'        => $url,
            'description' => $description,
            'metadata'    => $metadata,

            'ip'    => request()?->ip(),
            'agent' => request()?->userAgent(),

            'occurred_at' => now(),
        ]);
    }

    private function inferAction(array $segments, string $method): string
    {
        foreach ($segments as $segment) {
            $segment = Str::lower($segment);

            foreach (self::ACTION_KEYWORDS as $keyword => $action) {
                if (Str::contains($segment, $keyword)) {
                    return $action;
                }
            }
        }

        return self::METHOD_ACTIONS[$method] ?? 'action';
    }

    private function describe(string $action, ?string $module, array $identifiers = []): string
    {
        $moduleLabel = $module ? Str::of($module)->replace('-', ' ')->value() : 'the application';

        $description = ucfirst($action) . ' — ' . $moduleLabel;

        $identifier = $this->pickPrimaryIdentifier($identifiers);

        return $identifier ? "{$description} ({$identifier})" : $description;
    }

    // Walks up to two levels deep (covers a flat resource and a "grouped" one like
    // CustomerResource's {customer: {...}, address: {...}, ...}) collecting whichever
    // of IDENTIFIER_KEYS actually show up, without caring what shape any given
    // resource/request happens to be.
    private function extractIdentifiers(mixed $data, int $depth = 0): array
    {
        if (! is_array($data) || $depth > 1) {
            return [];
        }

        // A list (e.g. an index endpoint's collection) doesn't identify a single
        // record — nothing useful to pull out of it here.
        if (array_is_list($data)) {
            return [];
        }

        $found = [];

        foreach (self::IDENTIFIER_KEYS as $key) {
            if (isset($data[$key]) && (is_string($data[$key]) || is_int($data[$key]))) {
                $found[$key] = $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = array_merge($this->extractIdentifiers($value, $depth + 1), $found);
            }
        }

        return $found;
    }

    private function pickPrimaryIdentifier(array $identifiers): ?string
    {
        foreach (self::IDENTIFIER_KEYS as $key) {
            if (! empty($identifiers[$key])) {
                return (string) $identifiers[$key];
            }
        }

        return null;
    }

    // Strips files (not JSON-storable) and redacted keys recursively, leaving
    // everything else as submitted so an admin can see exactly what was sent.
    private function sanitize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return "[file: {$value->getClientOriginalName()}]";
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(Str::lower($key), self::REDACTED_KEYS, true)) {
                    continue;
                }

                $sanitized[$key] = $this->sanitize($item);
            }

            return $sanitized;
        }

        return $value;
    }
}
