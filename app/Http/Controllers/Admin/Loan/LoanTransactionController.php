<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Loan\LoanTransactionResource;
use App\Models\LoanTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Read-only, cross-loan view of every loan transaction ever posted — the sidebar-level
// "Transactions" page. Per-loan history is still available on a loan's own Transactions
// tab (LoanController::transactions()); this is the same rows, just not scoped to one
// loan, for browsing/searching across the whole portfolio.
class LoanTransactionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $transactions = LoanTransaction::query()
            ->with('createdBy')
            ->when(
                $request->string('type')->trim()->value(),
                fn ($query, $type) => $query->where('type', $type),
            )
            ->when(
                $request->string('search')->trim()->value(),
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('loan_account_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('transaction_reference', 'like', "%{$search}%");
                }),
            )
            ->latest()
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        return $this->success(
            message: 'Loan transactions retrieved successfully.',
            data: LoanTransactionResource::collection($transactions),
        );
    }
}
