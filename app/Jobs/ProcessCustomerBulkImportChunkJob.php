<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CustomerBulkImportRow;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

// Stage 2 of the bulk-import pipeline — processes one fixed-size slice of a batch's
// pending rows (see CustomerService::chunkBulkImportBatch()). A 6000-row upload becomes
// 30 of these instead of one job trying to create 6000 customers in a single run, which
// is what previously blew through the queue worker's timeout. Each chunk is
// independent: one slow or bad chunk never affects the others, and multiple chunks
// from the same batch can be worked in parallel by multiple queue workers.
class ProcessCustomerBulkImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // 200 rows/chunk, each involving a bcrypt hash + several uniqueness queries —
    // generous headroom well beyond a worst-case run.
    public int $timeout = 300;

    /** @param int[] $rowIds */
    public function __construct(
        public array $rowIds,
        public ?int $actorId = null,
    ) {}

    // create() (called per-row inside CustomerService::processBulkImportRowIds)
    // resolves the acting user from the 'user' auth guard for created_by/audit logging
    // — outside an HTTP request that guard is empty, so it's set explicitly here first,
    // same reason BulkApprovePostingsJob does this. actorId is looked up from the first
    // row in this chunk if not explicitly passed, since chunkBulkImportBatch() doesn't
    // carry it directly — every row in a batch already has the same created_by.
    public function handle(CustomerService $customers): void
    {
        $actorId = $this->actorId ?? CustomerBulkImportRow::whereIn('id', $this->rowIds)->value('created_by');
        $actor   = $actorId ? User::find($actorId) : null;

        if ($actor) {
            Auth::guard('user')->setUser($actor);
        }

        try {
            $customers->processBulkImportRowIds($this->rowIds);
        } catch (\Throwable $e) {
            report($e);

            CustomerBulkImportRow::query()
                ->whereIn('id', $this->rowIds)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'failed',
                    'error_reason' => 'This row could not be processed due to an unexpected error. Please try uploading again.',
                ]);
        }
    }
}
