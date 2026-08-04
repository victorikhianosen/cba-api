<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ThirdParty\Bvn\BvnService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BvnController extends Controller
{
    use ApiResponse;

    public function __construct(private BvnService $bvn) {}

    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'bvn' => ['required', 'digits:11'],
        ]);

        $data = $this->bvn->lookup($request->string('bvn')->toString());

        return $this->success(
            message: 'BVN lookup successful.',
            data: $data,
            responseCode: '000',
            statusCode: 200,
        );
    }
}
