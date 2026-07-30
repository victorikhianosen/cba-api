<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Posting\PostingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class BulkApprovePostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public array $postingIds,
        public int $actorId,
    ) {}

    // approve() (called per-posting inside PostingService::bulkApprove) resolves the
    // acting user from the 'user' auth guard for audit logging — outside an HTTP
    // request that guard is empty, so it's set explicitly here before approving.
    public function handle(PostingService $postings): void
    {
        $actor = User::find($this->actorId);

        if ($actor) {
            Auth::guard('user')->setUser($actor);
        }

        $postings->bulkApprove($this->postingIds);
    }
}
