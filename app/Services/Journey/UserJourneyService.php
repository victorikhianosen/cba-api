<?php

declare(strict_types=1);

namespace App\Services\Journey;

use App\Models\UserJourney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

// Read/list counterpart to JourneyService — same split as AuditService/AuditTrailService.
class UserJourneyService
{
    // One row per session (login → logout/timeout), summarized from the raw event
    // stream — the entry point for "which sessions happened", drilled into via timeline().
    public function sessions(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        $query = UserJourney::query()
            ->select('session_id')
            ->selectRaw('MAX(performed_by_name) as performed_by_name')
            ->selectRaw('MAX(performed_by_id) as performed_by_id')
            ->selectRaw('MIN(occurred_at) as started_at')
            ->selectRaw('MAX(occurred_at) as last_activity_at')
            ->selectRaw("MAX(CASE WHEN event_type IN ('logout', 'session_timeout') THEN occurred_at END) as ended_at")
            ->selectRaw("MAX(CASE WHEN event_type = 'session_timeout' THEN 1 ELSE 0 END) as timed_out")
            ->selectRaw('COUNT(*) as event_count')
            ->selectRaw('MAX(ip) as ip')
            ->groupBy('session_id');

        if ($search) {
            $query->having('performed_by_name', 'like', "%{$search}%");
        }

        return $query
            ->orderByDesc('started_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    // The full chronological timeline for a single session — everything captured
    // between login and logout/timeout, in the order it actually happened.
    public function timeline(string $sessionId): Collection
    {
        $events = UserJourney::query()
            ->where('session_id', $sessionId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            throw ValidationException::withMessages([
                'session_id' => ['No journey was found for this session.'],
            ]);
        }

        return $events;
    }
}
