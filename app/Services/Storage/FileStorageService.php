<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Single seam for every file upload in the app (bank logos, profile pictures, customer
// documents, product icons, ...). Swapping storage providers/buckets later — or moving
// off S3 entirely — means changing UPLOADS_DISK (and that disk's config in
// config/filesystems.php), not touching every service/resource that uploads a file.
class FileStorageService
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.uploads_disk', 's3');
    }

    public function upload(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, $this->disk);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk($this->disk)->url($path) : null;
    }
}
