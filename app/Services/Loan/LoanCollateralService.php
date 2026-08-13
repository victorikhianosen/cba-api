<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Services\Audit\AuditService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoanCollateralService
{
    public function __construct(
        private AuditService $audit,
        private FileStorageService $files,
    ) {}

    public function listForLoan(Loan $loan)
    {
        return $loan->collaterals()->latest()->get();
    }

    public function find(int $id): LoanCollateral
    {
        return LoanCollateral::with('loan')->findOrFail($id);
    }

    public function create(Loan $loan, array $data): LoanCollateral
    {
        $actor = Auth::guard('user')->user();

        if (isset($data['proof_of_ownership']) && $data['proof_of_ownership'] instanceof UploadedFile) {
            $data['proof_of_ownership_path'] = $this->files->upload($data['proof_of_ownership'], "loan-collaterals/{$loan->id}");
        }
        unset($data['proof_of_ownership']);

        $collateral = $loan->collaterals()->create([
            ...$data,
            'status'      => 'active',
            'created_by'  => $actor?->id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_collaterals',
            auditable: $collateral,
            after: $collateral->toArray(),
            description: "Collateral '{$collateral->type}' was pledged against loan '{$loan->account_number}' by '{$actor?->username}'.",
        );

        return $collateral;
    }

    public function update(LoanCollateral $collateral, array $data): LoanCollateral
    {
        $before = $collateral->toArray();
        $actor  = Auth::guard('user')->user();

        if (isset($data['proof_of_ownership']) && $data['proof_of_ownership'] instanceof UploadedFile) {
            $this->files->delete($collateral->proof_of_ownership_path);
            $data['proof_of_ownership_path'] = $this->files->upload($data['proof_of_ownership'], "loan-collaterals/{$collateral->loan_id}");
        }
        unset($data['proof_of_ownership'], $data['status'], $data['loan_id']);

        $collateral->update($data);
        $collateral->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'loan_collaterals',
            auditable: $collateral,
            before: $before,
            after: $collateral->toArray(),
            description: "Collateral #{$collateral->id} was updated by '{$actor?->username}'.",
        );

        return $collateral;
    }

    public function release(LoanCollateral $collateral, string $reason): LoanCollateral
    {
        if ($collateral->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only active collateral can be released.']]);
        }

        $actor = Auth::guard('user')->user();

        $collateral->update([
            'status'          => 'released',
            'released_at'     => now(),
            'released_by'     => $actor?->id,
            'release_reason'  => $reason,
        ]);

        $this->audit->log(
            action: 'released',
            module: 'loan_collaterals',
            auditable: $collateral,
            after: ['status' => 'released', 'reason' => $reason],
            description: "Collateral #{$collateral->id} was released by '{$actor?->username}': {$reason}",
        );

        return $collateral;
    }

    // Records that the collateral was sold/liquidated to recover an outstanding balance
    // — the realized proceeds themselves are booked through LoanRecoveryService, this
    // just marks the collateral's own lifecycle state.
    public function realize(LoanCollateral $collateral, string $amount): LoanCollateral
    {
        if ($collateral->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only active collateral can be realized.']]);
        }

        $actor = Auth::guard('user')->user();

        $collateral->update([
            'status'           => 'realized',
            'realized_at'      => now(),
            'realized_amount'  => $amount,
        ]);

        $this->audit->log(
            action: 'realized',
            module: 'loan_collaterals',
            auditable: $collateral,
            after: ['status' => 'realized', 'realized_amount' => $amount],
            description: "Collateral #{$collateral->id} was realized for ₦{$amount} by '{$actor?->username}'.",
        );

        return $collateral;
    }
}
