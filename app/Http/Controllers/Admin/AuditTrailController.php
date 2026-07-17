<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditTrail\AuditTrailResource;
use App\Services\Audit\AuditTrailService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditTrailService $auditTrails,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $auditTrails = $this->auditTrails->list(
            $request->integer('per_page', 20),
            $request->string('search')->value() ?: null,
        );

        return $this->success(
            message: 'Audit trails retrieved successfully.',
            data: AuditTrailResource::collection($auditTrails),
        );
    }

    public function show(int $id): JsonResponse
    {
        $auditTrail = $this->auditTrails->find($id);

        return $this->success(
            message: 'Audit trail retrieved successfully.',
            data: new AuditTrailResource($auditTrail),
        );
    }
}
