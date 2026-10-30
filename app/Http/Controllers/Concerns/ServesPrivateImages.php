<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ImageProcessor;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands out a picture kept on the private disk, once the controller has
 * checked that the viewer may see what it belongs to. Each picture's
 * address names its file, and a file is never changed in place (a new
 * picture gets a new name), so a browser may keep it for good: it fetches
 * each picture once.
 */
trait ServesPrivateImages
{
    protected function privateImage(?string $path): StreamedResponse
    {
        $disk = Storage::disk(ImageProcessor::PRIVATE_DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
