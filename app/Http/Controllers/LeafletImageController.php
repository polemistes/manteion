<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPrivateImages;
use App\Models\Leaflet;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeafletImageController extends Controller
{
    use ServesPrivateImages;

    /**
     * A leaflet's picture, for whoever may read the leaflet. The address
     * names the file, so a replaced or removed picture is gone from it.
     */
    public function show(Leaflet $leaflet, string $file): StreamedResponse
    {
        Gate::authorize('view', $leaflet);

        abort_unless($leaflet->image_path !== null && basename($leaflet->image_path) === $file, 404);

        return $this->privateImage($leaflet->image_path);
    }
}
