<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Status\StatusResource;
use App\Models\Status;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    use ApiResponse;

    // Flat reference list (no pagination) — every status a customer/account can be set to,
    // used to populate the status dropdown on the "update status" modal.
    public function index(): JsonResponse
    {
        $statuses = Status::orderBy('category')->orderBy('name')->get();

        return $this->success(
            message: 'Statuses retrieved successfully.',
            data: StatusResource::collection($statuses),
        );
    }
}
