<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\UpdateSettingRequest;
use App\Http\Requests\Admin\Setting\UploadSettingImageRequest;
use App\Http\Resources\Admin\Setting\SettingResource;
use App\Models\Setting;
use App\Services\Setting\SettingService;
use App\Services\Storage\FileStorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private SettingService $settings,
        private FileStorageService $files,
    ) {}

    // Any authenticated user (not gated behind view_settings) — the app shell (sidebar
    // logo/company name) needs this on every page for every role, not just admins who can
    // manage settings. Reachable in the 'unlocked' route group same as session/lock-config,
    // since the shell renders underneath the lock overlay too.
    public function branding(): JsonResponse
    {
        return $this->success(
            message: 'Branding retrieved successfully.',
            data: [
                'name'     => $this->settings->get('system.company.name', 'BankPro'),
                'logo_url' => $this->files->url($this->settings->get('system.company.logo')),
            ],
        );
    }

    public function index(Request $request): JsonResponse
    {
        $settings = $this->settings->allByCategory(
            $request->string('category')->trim()->value() ?: null,
        )->load('updatedBy');

        return $this->success(
            message: 'Settings retrieved successfully.',
            data: SettingResource::collection($settings),
        );
    }

    public function show(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $this->error(
                message: 'The requested setting was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }

        return $this->success(
            message: 'Setting retrieved successfully.',
            data: new SettingResource($setting->load('updatedBy')),
        );
    }

    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $this->error(
                message: 'The requested setting was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }

        try {
            $setting = $this->settings->update($setting, $request->validated()['value']);

            return $this->success(
                message: 'Setting updated successfully.',
                data: new SettingResource($setting->load('updatedBy')),
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

    // Separate from update() because this setting's "value" is a storage path behind an
    // uploaded file, not a plain string a JSON PUT body can carry — mirrors how
    // BankController::uploadLogo() is a dedicated endpoint distinct from Bank's normal update.
    public function uploadImage(UploadSettingImageRequest $request, string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $this->error(
                message: 'The requested setting was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }

        if ($setting->type !== 'image') {
            return $this->error(
                message: "The setting '{$key}' does not accept an image upload.",
                responseCode: '101',
                statusCode: 422,
            );
        }

        try {
            $before = $setting->value;
            $path   = $this->files->upload($request->file('image'), 'setting-images');

            $setting = $this->settings->update($setting, $path);
            $this->files->delete($before);

            return $this->success(
                message: 'Setting image updated successfully.',
                data: new SettingResource($setting->load('updatedBy')),
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
