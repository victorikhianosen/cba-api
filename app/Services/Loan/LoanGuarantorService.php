<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Storage\FileStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoanGuarantorService
{
    public function __construct(
        private AuditService $audit,
        private NotificationService $notifications,
        private FileStorageService $files,
    ) {}

    public function listForLoan(Loan $loan)
    {
        return $loan->guarantors()->latest()->get();
    }

    public function find(int $id): LoanGuarantor
    {
        return LoanGuarantor::with(['loan', 'customer'])->findOrFail($id);
    }

    public function create(Loan $loan, array $data): LoanGuarantor
    {
        $actor = Auth::guard('user')->user();

        if (isset($data['valid_id']) && $data['valid_id'] instanceof UploadedFile) {
            $data['valid_id_path'] = $this->files->upload($data['valid_id'], "loan-guarantors/{$loan->id}");
        }
        unset($data['valid_id']);

        if (isset($data['signed_guarantee_letter']) && $data['signed_guarantee_letter'] instanceof UploadedFile) {
            $data['signed_guarantee_letter_path'] = $this->files->upload($data['signed_guarantee_letter'], "loan-guarantors/{$loan->id}");
        }
        unset($data['signed_guarantee_letter']);

        $guarantor = $loan->guarantors()->create([
            ...$data,
            'status'      => 'pending',
            'created_by'  => $actor?->id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_guarantors',
            auditable: $guarantor,
            after: $guarantor->toArray(),
            description: "Guarantor '{$guarantor->display_name}' was added to loan '{$loan->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_loans',
            'loan_guarantor',
            $guarantor->id,
            'New guarantor pending approval',
            "Guarantor '{$guarantor->display_name}' on loan '{$loan->account_number}' is awaiting approval.",
        );

        return $guarantor;
    }

    public function approve(LoanGuarantor $guarantor): LoanGuarantor
    {
        if ($guarantor->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending guarantor can be approved.']]);
        }

        $actor = Auth::guard('user')->user();

        $guarantor->update([
            'status'      => 'approved',
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ]);

        $this->audit->log(
            action: 'approved',
            module: 'loan_guarantors',
            auditable: $guarantor,
            after: ['status' => 'approved'],
            description: "Guarantor '{$guarantor->display_name}' was approved by '{$actor?->username}'.",
        );

        return $guarantor;
    }

    public function reject(LoanGuarantor $guarantor, string $reason): LoanGuarantor
    {
        if ($guarantor->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending guarantor can be rejected.']]);
        }

        $actor = Auth::guard('user')->user();

        $guarantor->update(['status' => 'rejected']);

        $this->audit->log(
            action: 'rejected',
            module: 'loan_guarantors',
            auditable: $guarantor,
            after: ['status' => 'rejected', 'reason' => $reason],
            description: "Guarantor '{$guarantor->display_name}' was rejected by '{$actor?->username}': {$reason}",
        );

        return $guarantor;
    }

    public function release(LoanGuarantor $guarantor): LoanGuarantor
    {
        if ($guarantor->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['Only an approved guarantor can be released.']]);
        }

        $actor = Auth::guard('user')->user();

        $guarantor->update(['status' => 'released', 'released_at' => now()]);

        $this->audit->log(
            action: 'released',
            module: 'loan_guarantors',
            auditable: $guarantor,
            after: ['status' => 'released'],
            description: "Guarantor '{$guarantor->display_name}' was released by '{$actor?->username}'.",
        );

        return $guarantor;
    }

    // Marks the guarantee as called on (the borrower defaulted and the guarantor is now
    // liable) — a claim doesn't itself move money; recovering funds from the guarantor
    // still goes through the normal repayment/recovery flow against the loan.
    public function claim(LoanGuarantor $guarantor, string $amount): LoanGuarantor
    {
        if ($guarantor->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['Only an approved guarantor can be claimed against.']]);
        }

        $actor = Auth::guard('user')->user();

        $guarantor->update([
            'claimed'         => true,
            'claimed_amount'  => $amount,
            'claimed_at'      => now(),
        ]);

        $this->audit->log(
            action: 'claimed',
            module: 'loan_guarantors',
            auditable: $guarantor,
            after: ['claimed' => true, 'claimed_amount' => $amount],
            description: "Guarantee from '{$guarantor->display_name}' was claimed for ₦{$amount} by '{$actor?->username}'.",
        );

        return $guarantor;
    }

    // Blank, fillable guarantee letter — signed by hand (or digitally) then re-uploaded
    // as signed_guarantee_letter when the guarantor is added. Same dompdf pattern
    // AccountService::renderStatementPdf() uses for statement PDFs.
    public function renderGuaranteeLetterTemplate(string $guaranteeType): string
    {
        $view = $guaranteeType === 'corporate' ? 'loans.guarantee-letter-corporate' : 'loans.guarantee-letter-personal';

        return Pdf::loadView($view)->setPaper('a4', 'portrait')->output();
    }
}
