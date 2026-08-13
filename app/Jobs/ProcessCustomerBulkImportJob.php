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

// Stage 1 of the bulk-import pipeline: reads the uploaded spreadsheet (none of this
// happens during the HTTP request — CustomerService::startBulkImport() only stores the
// file and dispatches this job) and snapshots every row as a pending
// CustomerBulkImportRow, then hands the actual per-row work off to many small
// ProcessCustomerBulkImportChunkJob dispatches (see CustomerService::chunkBulkImportBatch()).
// Splitting is what fixes the real failure mode this replaced: a single job trying to
// create thousands of customers (each involving a bcrypt password hash) blew straight
// through the queue worker's default 60s timeout partway through a 6000-row file.
class ProcessCustomerBulkImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // Reading and inserting rows is comparatively cheap (no bcrypt, no per-row
    // validation queries) — this only needs headroom for very large files, not the
    // 60s default.
    public int $timeout = 600;

    public function __construct(
        public string $batchId,
        public string $storedPath,
        public string $fileName,
        public int $actorId,
    ) {}

    public function handle(CustomerService $customers): void
    {
        $actor = User::find($this->actorId);

        if ($actor) {
            Auth::guard('user')->setUser($actor);
        }

        try {
            $customers->populateBulkImportRows($this->batchId, $this->storedPath, $this->fileName, $actor?->id);
            $customers->chunkBulkImportBatch($this->batchId);
        } catch (\Throwable $e) {
            report($e);

            // Failed before any chunk jobs got dispatched, so nothing else will ever
            // pick this batch up — fail out whatever rows did get written (a corrupt
            // file partway through parsing can leave some), or if there are none at
            // all, leave one row behind so the batch is still discoverable in the
            // list/detail views instead of silently vanishing.
            $stillPending = CustomerBulkImportRow::query()
                ->where('batch_id', $this->batchId)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'failed',
                    'error_reason' => 'This batch could not be processed due to an unexpected error. Please try uploading again.',
                ]);

            if ($stillPending === 0 && ! CustomerBulkImportRow::where('batch_id', $this->batchId)->exists()) {
                CustomerBulkImportRow::create([
                    'batch_id'     => $this->batchId,
                    'file_name'    => $this->fileName,
                    'row_number'   => 0,
                    'status'       => 'failed',
                    'row_data'     => [],
                    'error_reason' => 'This file could not be read. Please check it matches the template and try uploading again.',
                    'created_by'   => $this->actorId,
                ]);
            }
        }
    }
}
