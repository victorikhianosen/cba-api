<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvestmentLetterTemplate\StoreInvestmentLetterTemplateRequest;
use App\Http\Requests\Admin\InvestmentLetterTemplate\UpdateInvestmentLetterTemplateRequest;
use App\Http\Resources\Admin\InvestmentLetterTemplate\InvestmentLetterTemplateResource;
use App\Services\Audit\AuditService;
use App\Services\Investment\InvestmentLetterTemplateService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvestmentLetterTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private InvestmentLetterTemplateService $templates,
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $templates = $this->templates->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Investment letter templates retrieved successfully.',
            data: InvestmentLetterTemplateResource::collection($templates),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(
                message: 'Investment letter template retrieved successfully.',
                data: new InvestmentLetterTemplateResource($this->templates->find($id)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested template was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function store(StoreInvestmentLetterTemplateRequest $request): JsonResponse
    {
        try {
            $data               = $request->validated();
            $data['is_default'] = $request->boolean('is_default');

            $template = $this->templates->create($data);

            $this->audit->log(
                action: 'created',
                module: 'investment_letter_templates',
                auditable: $template,
                after: $template->toArray(),
                description: "Investment letter template '{$template->name}' was created.",
            );

            return $this->success(
                message: 'Investment letter template created successfully.',
                data: new InvestmentLetterTemplateResource($template),
                responseCode: '000',
                statusCode: 201,
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

    public function update(UpdateInvestmentLetterTemplateRequest $request, int $id): JsonResponse
    {
        try {
            $template = $this->templates->find($id);
            $before   = $template->toArray();

            $data               = $request->validated();
            $data['is_default'] = $request->boolean('is_default');

            $template = $this->templates->update($template, $data);

            $this->audit->log(
                action: 'updated',
                module: 'investment_letter_templates',
                auditable: $template,
                before: $before,
                after: $template->toArray(),
                description: "Investment letter template '{$template->name}' was updated.",
            );

            return $this->success(
                message: 'Investment letter template updated successfully.',
                data: new InvestmentLetterTemplateResource($template),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested template was not found.',
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

    public function destroy(int $id): JsonResponse
    {
        try {
            $template = $this->templates->find($id);
            $name     = $template->name;

            $this->templates->delete($template);

            $this->audit->log(
                action: 'deleted',
                module: 'investment_letter_templates',
                before: ['name' => $name],
                description: "Investment letter template '{$name}' was deleted.",
            );

            return $this->success(
                message: 'Investment letter template deleted successfully.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested template was not found.',
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

    public function setDefault(int $id): JsonResponse
    {
        try {
            $template = $this->templates->find($id);
            $template = $this->templates->setDefault($template);

            $this->audit->log(
                action: 'set_default',
                module: 'investment_letter_templates',
                auditable: $template,
                after: ['is_default' => true],
                description: "Investment letter template '{$template->name}' was set as the default.",
            );

            return $this->success(
                message: 'Default investment letter template updated successfully.',
                data: new InvestmentLetterTemplateResource($template),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested template was not found.',
                responseCode: '404',
                statusCode: 404,
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
