<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teller\AssignTellerRequest;
use App\Http\Requests\Admin\Teller\StoreTellerRequest;
use App\Http\Requests\Admin\Teller\UpdateTellerRequest;
use App\Http\Requests\Admin\Teller\UpdateTellerStatusRequest;
use App\Http\Resources\Admin\Teller\TellerResource;
use App\Services\Teller\TellerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TellerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TellerService $tellers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tellers = $this->tellers->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Tellers retrieved successfully.',
            data: TellerResource::collection($tellers),
        );
    }

    public function show(int $id): JsonResponse
    {
        $teller = $this->tellers->find($id);

        return $this->success(
            message: 'Teller retrieved successfully.',
            data: new TellerResource($teller),
        );
    }

    public function store(StoreTellerRequest $request): JsonResponse
    {
        try {
            $teller = $this->tellers->create($request->validated());

            return $this->success(
                message: 'Teller created successfully.',
                data: new TellerResource($teller),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function update(UpdateTellerRequest $request, int $id): JsonResponse
    {
        $teller = $this->tellers->find($id);

        try {
            $teller = $this->tellers->update($teller, $request->validated());

            return $this->success(message: 'Teller updated successfully.', data: new TellerResource($teller));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        $teller = $this->tellers->find($id);

        try {
            $teller = $this->tellers->approve($teller);

            return $this->success(message: 'Teller approved successfully.', data: new TellerResource($teller));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function updateStatus(UpdateTellerStatusRequest $request, int $id): JsonResponse
    {
        $teller = $this->tellers->find($id);

        try {
            $teller = $this->tellers->updateStatus($teller, $request->validated()['status']);

            return $this->success(message: 'Teller status updated successfully.', data: new TellerResource($teller));
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function assign(AssignTellerRequest $request, int $id): JsonResponse
    {
        $teller = $this->tellers->find($id);

        try {
            $this->tellers->assign($teller, (int) $request->validated()['user_id']);

            return $this->success(message: 'Teller assigned successfully.', data: new TellerResource($this->tellers->find($id)));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function unassign(int $id): JsonResponse
    {
        $teller = $this->tellers->find($id);

        try {
            $this->tellers->unassign($teller);

            return $this->success(message: 'Teller unassigned successfully.', data: new TellerResource($this->tellers->find($id)));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }
}
