<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserJourney\UserJourneyEventResource;
use App\Http\Resources\Admin\UserJourney\UserJourneySessionResource;
use App\Services\Journey\UserJourneyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserJourneyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private UserJourneyService $journeys,
    ) {}

    // Lists sessions (login → logout/timeout), most recent first — the entry point
    // for "who did what, in which session", drilled into via show().
    public function index(Request $request): JsonResponse
    {
        $sessions = $this->journeys->sessions(
            $request->integer('per_page', 20),
            $request->string('search')->value() ?: null,
        );

        return $this->success(
            message: 'User journey sessions retrieved successfully.',
            data: UserJourneySessionResource::collection($sessions),
        );
    }

    // The full chronological timeline for one session.
    public function show(string $sessionId): JsonResponse
    {
        try {
            $events = $this->journeys->timeline($sessionId);

            return $this->success(
                message: 'User journey retrieved successfully.',
                data: UserJourneyEventResource::collection($events),
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '404',
                statusCode: 404,
                errors: $e->errors(),
            );
        }
    }
}
