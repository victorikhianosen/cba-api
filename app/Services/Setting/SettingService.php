<?php

declare(strict_types=1);

namespace App\Services\Setting;

use App\Models\Setting;
use App\Services\Audit\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class SettingService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    // Cached forever (invalidated on update) since settings change rarely but are read on
    // nearly every request — a raw DB lookup per call would not scale.
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $setting = Setting::where('key', $key)->where('status', 'active')->first();

            return $setting ? $setting->resolvedValue() : $default;
        });
    }

    public function allByCategory(?string $category = null): Collection
    {
        return Setting::query()
            ->where('status', 'active')
            ->when($category, fn ($query, $category) => $query->where('category', $category))
            ->orderBy('category')
            ->orderBy('key')
            ->get();
    }

    public function update(Setting $setting, string $newValue): Setting
    {
        if (! $setting->editable) {
            throw ValidationException::withMessages([
                'value' => ['This setting is not editable.'],
            ]);
        }

        $oldValue = $setting->value;
        $masked = $setting->sensitive || $setting->encrypted;

        $setting->value = $setting->encrypted ? Crypt::encryptString($newValue) : $newValue;
        $setting->updated_by = Auth::guard('user')->user()?->id;
        $setting->save();

        Cache::forget("setting:{$setting->key}");

        $this->audit->log(
            action: 'updated',
            module: 'settings',
            auditable: $setting,
            description: $masked
                ? "Setting '{$setting->key}' was updated from '••••••' to '••••••'."
                : "Setting '{$setting->key}' was updated from '{$oldValue}' to '{$newValue}'.",
        );

        return $setting;
    }
}
