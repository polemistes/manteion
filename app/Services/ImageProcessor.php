<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageProcessor
{
    /**
     * Re-encodes an uploaded image through GD and stores it as a JPEG.
     * Decoding and re-encoding never carries EXIF metadata through (GD has
     * no concept of it), so this strips camera/GPS metadata as a side
     * effect — a defensive backstop behind the client-side cropper, which
     * already strips it by rendering through <canvas>.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $image = $this->decode($file);

        $width = imagesx($image);
        $height = imagesy($image);

        $flattened = imagecreatetruecolor($width, $height);
        imagealphablending($flattened, true);

        $white = imagecolorallocate($flattened, 255, 255, 255);

        if ($white === false) {
            throw new RuntimeException('Could not allocate the background color.');
        }

        imagefill($flattened, 0, 0, $white);
        imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($flattened, null, 85);
        $contents = ob_get_clean();

        $path = trim($directory, '/').'/'.Str::random(40).'.jpg';

        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private function decode(UploadedFile $file): GdImage
    {
        $image = match ($file->getMimeType()) {
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => imagecreatefromjpeg($file->getRealPath()),
        };

        if ($image === false) {
            throw new RuntimeException('Could not read the uploaded image.');
        }

        return $image;
    }
}
