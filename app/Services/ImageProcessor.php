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
     * The largest edge stored for an event photo, and for an avatar. Uploads
     * are scaled down to fit; nothing is ever scaled up. The client-side
     * cropper already emits these sizes, so this only bites for uploads
     * that bypass it.
     */
    public const EVENT_MAX_EDGE = 1600;

    public const AVATAR_MAX_EDGE = 512;

    /**
     * Re-encodes an uploaded image through GD, scaled to fit within
     * `$maxEdge` pixels on its longer side, and stores it as a JPEG.
     * Decoding and re-encoding never carries EXIF metadata through (GD has
     * no concept of it), so this strips camera/GPS metadata as a side
     * effect — a defensive backstop behind the client-side cropper, which
     * already strips it by rendering through <canvas>. Orientation is
     * applied first where the exif extension is available, so a stripped
     * photo is not left rotated.
     */
    public function store(UploadedFile $file, string $directory, int $maxEdge = self::EVENT_MAX_EDGE): string
    {
        $image = $this->orient($this->decode($file), $file);
        $image = $this->fit($image, $maxEdge);

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

    private function fit(GdImage $image, int $maxEdge): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxEdge && $height <= $maxEdge) {
            return $image;
        }

        $scale = $maxEdge / max($width, $height);
        $scaled = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        if ($scaled === false) {
            throw new RuntimeException('Could not scale the uploaded image.');
        }

        return $scaled;
    }

    private function orient(GdImage $image, UploadedFile $file): GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated === false ? $image : $rotated;
    }
}
