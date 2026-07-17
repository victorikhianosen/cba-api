<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\AssignUserRolesRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Http\Requests\Admin\User\UpdateUserStatusRequest;
use App\Http\Requests\Admin\User\UploadProfilePictureRequest;
use App\Http\Resources\Admin\User\UserResource;
use App\Models\User;
use App\Services\User\UserService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private UserService $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->users->list(
            $request->integer('per_page', 15)
        );

        return $this->success(
            message: 'Users retrieved successfully.',
            data: UserResource::collection($users),
            meta: [
                'summary' => [
                    'total_users' => $users->total(),

                    'total_active' => User::query()
                        ->where('status', 'active')
                        ->count(),

                    'total_pending' => User::query()
                        ->where('status', 'pending')
                        ->count(),

                    'total_inactive' => User::query()
                        ->where('status', 'inactive')
                        ->count(),
                ],
            ],
        );
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->users->find($id);

        return $this->success(
            message: 'User retrieved successfully.',
            data: new UserResource($user),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $user = $this->users->create($request->validated());

            return $this->success(
                message: 'User created successfully.',
                data: new UserResource($user),
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

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->users->update($id, $request->validated());

            return $this->success(
                message: 'User updated successfully.',
                data: new UserResource($user),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested user was not found.',
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

    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->users->updateStatus($id, $request->validated()['status']);
            return $this->success(
                message: 'User status updated successfully.',
                data: new UserResource($user),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested user was not found.',
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

    public function uploadProfilePicture(UploadProfilePictureRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->users->uploadProfilePicture($id, $request->file('profile_picture'));

            return $this->success(
                message: 'Profile picture updated successfully.',
                data: new UserResource($user),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested user was not found.',
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

    public function resetPassword(int $id): JsonResponse
    {
        try {
            $user = $this->users->resetPassword($id);

            return $this->success(
                message: 'Password reset successfully. The new password has been emailed to the user.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested user was not found.',
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

    public function assignRoles(AssignUserRolesRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->users->find($id);
            $user = $this->users->assignRoles($user, $request->validated()['roles']);

            return $this->success(
                message: 'Role(s) assigned to user successfully.',
                data: new UserResource($user),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested user was not found.',
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

    public function removeRoles(AssignUserRolesRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->users->find($id);
            $user = $this->users->removeRoles($user, $request->validated()['roles']);

            return $this->success(
                message: 'Role(s) removed from user successfully.',
                data: new UserResource($user),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested user was not found.',
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
