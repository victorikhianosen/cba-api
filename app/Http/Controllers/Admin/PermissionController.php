<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Permission\StorePermissionRequest;
use App\Http\Requests\Admin\Permission\UpdatePermissionRequest;
use App\Http\Resources\Admin\Permission\PermissionResource;
use App\Services\Permission\PermissionService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PermissionService $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $permissions = $this->permissions->list(
            $request->string('search')->value() ?: null
        );

        return $this->success(
            message: 'Permissions retrieved successfully.',
            data: PermissionResource::collection($permissions),
            meta: [
                'summary' => [
                    'total_permissions' => $permissions->count(),
                    'total_assigned' => Permission::has('roles')->count(),
                    'total_unassigned' => Permission::doesntHave('roles')->count(),
                ],
            ],
        );
    }


    // public function index(Request $request): JsonResponse
    // {
    //     $permissions = $this->permissions->list($request->integer('per_page', 15), $request->string('search')->value() ?: null);

    //     return $this->success(
    //         message: 'Permissions retrieved successfully.',
    //         data: PermissionResource::collection($permissions),
    //         meta: [
    //             'summary' => [
    //                 'total_permissions' => $permissions->total(),

    //                 'total_assigned' => Permission::query()
    //                     ->whereHas('roles')
    //                     ->count(),

    //                 'total_unassigned' => Permission::query()
    //                     ->doesntHave('roles')
    //                     ->count(),
    //             ],
    //         ],
    //     );
    // }

    public function show(int $id): JsonResponse
    {
        $permission = $this->permissions->find($id);

        return $this->success(
            message: 'Permission retrieved successfully.',
            data: new PermissionResource($permission),
        );
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        try {
            $permission = $this->permissions->create($request->validated());

            return $this->success(
                message: 'Permission created successfully.',
                data: new PermissionResource($permission),
                responseCode: '000',
                statusCode: 201,
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

    public function update(UpdatePermissionRequest $request, int $id): JsonResponse
    {
        try {
            $permission = $this->permissions->find($id);
            $permission = $this->permissions->update($permission, $request->validated());

            return $this->success(
                message: 'Permission updated successfully.',
                data: new PermissionResource($permission),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested permission was not found.',
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
            $permission = $this->permissions->find($id);

            $this->permissions->delete($permission);

            return $this->success(
                message: 'Permission deleted successfully.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested permission was not found.',
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
