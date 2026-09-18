<?php

namespace App\Jobs;

use App\Models\Leaflet;
use App\Services\LinkPreview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fills in a leaflet's link preview off the request. A failed or empty
 * fetch still marks the link as fetched, so the page shows a plain link
 * rather than waiting for a preview that will never come.
 */
class FetchLeafletLinkPreview implements ShouldQueue
{
    use Queueable;

    public function __construct(public Leaflet $leaflet) {}

    public function handle(LinkPreview $linkPreview): void
    {
        $leaflet = $this->leaflet->fresh();

        if ($leaflet === null || $leaflet->link_url === null) {
            return;
        }

        $preview = $linkPreview->fetch($leaflet->link_url);

        $leaflet->forceFill([
            'link_title' => $preview['title'] ?? null,
            'link_description' => $preview['description'] ?? null,
            'link_image_url' => $preview['image_url'] ?? null,
            'link_fetched_at' => now(),
        ])->save();
    }
}
