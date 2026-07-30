<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Posting\RejectPostingRequest;
use App\Http\Requests\Admin\Posting\StorePostingRequest;
use App\Http\Resources\Admin\Posting\FinancialTransactionResource;
use App\Http\Resources\Admin\Posting\PostingResource;
use App\Jobs\BulkApprovePostingsJob;
use App\Services\Posting\PostingService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PostingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PostingService $postings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $postings = $this->postings->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Postings retrieved successfully.',
            data: PostingResource::collection($postings),
        );
    }

    public function show(int $id): JsonResponse
    {
        $posting = $this->postings->find($id);

        return $this->success(
            message: 'Posting retrieved successfully.',
            data: new PostingResource($posting),
        );
    }

    public function store(StorePostingRequest $request): JsonResponse
    {
        try {
            $posting = $this->postings->create($request->validated());

            return $this->success(
                message: 'Posting created successfully and is pending approval.',
                data: new PostingResource($posting),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested source or destination was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    // Approving is the only action that creates the financial transaction and its ledger legs, all inside one transaction in PostingService::approve().
    public function approve(int $id): JsonResponse
    {
        try {
            $posting              = $this->postings->find($id);
            $financialTransaction = $this->postings->approve($posting);

            return $this->success(
                message: 'Posting approved and posted successfully.',
                data: new FinancialTransactionResource($financialTransaction),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested posting, source, or destination was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    // Approves any mix of the four posting-type combinations (GL↔GL, account↔account,
    // account↔GL, GL↔account) as long as each posting is pending — the type itself
    // never matters to approval, only the status does. Dispatched to a queued job so
    // the request returns immediately instead of blocking on up to 50 sequential
    // approvals; the caller just gets told the batch is on its way.
    public function bulkApprove(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        $actor = Auth::guard('user')->user();

        BulkApprovePostingsJob::dispatch($ids, (int) $actor?->id);

        return $this->success(
            message: count($ids) . ' posting(s) queued for approval — they will update shortly.',
        );
    }

    public function reject(RejectPostingRequest $request, int $id): JsonResponse
    {
        try {
            $posting = $this->postings->find($id);
            $posting = $this->postings->reject($posting, $request->validated()['reason']);

            return $this->success(
                message: 'Posting rejected successfully.',
                data: new PostingResource($posting),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested posting was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }
}
