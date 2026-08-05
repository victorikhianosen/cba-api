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

    // Raw file contents — used where a URL isn't reliable (e.g. embedding an image as a
    // base64 data URI in a dompdf-rendered PDF, which can't dependably fetch remote URLs).
    public function read(?string $path): ?string
    {
        return $path && Storage::disk($this->disk)->exists($path)
            ? Storage::disk($this->disk)->get($path)
            : null;
    }

    // For uploads that are transformed in memory (e.g. background removal) before being
    // written, so there's no UploadedFile on disk to hand to store().
    public function putContents(string $contents, string $directory, string $extension): string
    {
        $path = trim($directory, '/') . '/' . uniqid('', true) . '.' . $extension;
        Storage::disk($this->disk)->put($path, $contents);

        return $path;
    }

    // Header/logo/signature images are typically photographed or scanned on a plain
    // white (or near-white) sheet — that background reads as an ugly grey box once
    // embedded in a dompdf-rendered PDF, so any pixel close to white is made transparent
    // before storing. Output is always PNG since JPEG has no alpha channel. Used by any
    // template feature (investment letters, account statements, ...) that lets an admin
    // upload letterhead artwork.
    public function uploadImageWithBackgroundRemoved(UploadedFile $file, string $directory): string
    {
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($source === false) {
            return $this->upload($file, $directory);
        }

        $width  = imagesx($source);
        $height = imagesy($source);

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $threshold = 235;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8) & 0xFF;
                $b   = $rgb & 0xFF;

                if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
                    imagesetpixel($image, $x, $y, $transparent);
                }
            }
        }

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $this->putContents($contents, $directory, 'png');
    }
}
