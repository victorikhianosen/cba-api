<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

trait ApiResponse
{

    protected function success(
        string $message = 'Successful',
        mixed $data = null,
        string $responseCode = '000',
        int $statusCode = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'status'       => 'success',
            'responseCode' => $responseCode,
            'message'      => $message,
        ];

        if ($data instanceof ResourceCollection || $data instanceof JsonResource) {
            $payload = array_merge($payload, $this->fromResource($data));
        } elseif ($data instanceof AbstractPaginator) {
            $payload['data'] = $data->items();
            $payload['meta'] = $this->paginationMeta($data);
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        if ($meta) {
            $payload['meta'] = array_merge($payload['meta'] ?? [], $meta);
        }

        return response()->json($payload, $statusCode);
    }


    protected function error(
        string $message = 'Request failed',
        string $responseCode = '999',
        int $statusCode = 400,
        mixed $errors = null,
    ): JsonResponse {
        $payload = [
            'status'       => 'error',
            'responseCode' => $responseCode,
            'message'      => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }


    private function fromResource(JsonResource|ResourceCollection $resource): array
{
    $resolved = $resource->resolve();
    $out = ['data' => $resolved];

    $paginator = $resource->resource;
    if ($paginator instanceof AbstractPaginator) {
        $out['meta']  = $this->paginationMeta($paginator);
        $out['links'] = $this->paginationLinks($paginator);
    }

    return $out;
}

private function paginationMeta(AbstractPaginator $paginator): array
{
    return [
        'current_page' => $paginator->currentPage(),
        'per_page'     => $paginator->perPage(),
        'total'        => method_exists($paginator, 'total') ? $paginator->total() : null,
        'last_page'    => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : null,
        'has_more'     => $paginator->hasMorePages(),
        'from'         => $paginator->firstItem(),
        'to'           => $paginator->lastItem(),
    ];
}

private function paginationLinks(AbstractPaginator $paginator): array
{
    return [
        'first' => $paginator->url(1),
        'last'  => method_exists($paginator, 'lastPage') ? $paginator->url($paginator->lastPage()) : null,
        'prev'  => $paginator->previousPageUrl(),
        'next'  => $paginator->nextPageUrl(),
    ];
}

}