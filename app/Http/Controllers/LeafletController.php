<?php

namespace App\Http\Controllers;

use App\Enums\LeafletVisibility;
use App\Enums\ReportStatus;
use App\Http\Requests\StoreLeafletRequest;
use App\Http\Requests\UpdateLeafletRequest;
use App\Jobs\FetchLeafletLinkPreview;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\Reaction;
use App\Models\User;
use App\Services\ActivityFeed;
use App\Services\ImageProcessor;
use App\Services\LeafletFeedQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LeafletController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private ImageProcessor $imageProcessor) {}

    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $scope = LeafletVisibility::tryFrom($request->string('scope')->toString());

        $leaflets = (new LeafletFeedQuery($viewer))
            ->visible($scope)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $leaflets->getCollection()->each(fn (Leaflet $leaflet) => $this->retryMissingLinkPreview($leaflet));

        return Inertia::render('leaflets/Index', [
            'leaflets' => $leaflets->getCollection()->map(fn (Leaflet $leaflet) => $this->summarize($leaflet, $viewer))->values(),
            'pagination' => [
                'current_page' => $leaflets->currentPage(),
                'last_page' => $leaflets->lastPage(),
                'total' => $leaflets->total(),
                'previous_url' => $leaflets->previousPageUrl(),
                'next_url' => $leaflets->nextPageUrl(),
            ],
            'scope' => $scope?->value,
        ]);
    }

    public function store(StoreLeafletRequest $request): RedirectResponse
    {
        Gate::authorize('create', Leaflet::class);

        $leaflet = $request->user()->leaflets()->create([
            ...$request->safe()->only(['body', 'visibility']),
            'link_url' => $request->validated('link_url') ?: null,
        ]);

        $this->storeImage($leaflet, $request->file('image'));
        $this->refreshLinkPreview($leaflet);

        return redirect()->route('leaflets.index');
    }

    public function show(Request $request, Leaflet $leaflet): Response
    {
        Gate::authorize('view', $leaflet);

        $leaflet->load(['owner', 'reactions', 'responses.user', 'responses.reactions'])->loadCount('responses');
        $this->retryMissingLinkPreview($leaflet);

        // Opening the leaflet means the replies to it have been seen.
        if (ActivityFeed::seenByUser($request)) {
            ActivityFeed::markRead($request->user(), 'leaflet', $leaflet->id);
        }

        return Inertia::render('leaflets/Show', [
            'leaflet' => $this->summarize($leaflet, $request->user()),
        ]);
    }

    public function edit(Leaflet $leaflet): Response
    {
        Gate::authorize('update', $leaflet);

        return Inertia::render('leaflets/Edit', [
            'leaflet' => [
                'id' => $leaflet->id,
                'body' => $leaflet->body,
                'visibility' => $leaflet->visibility->value,
                'link_url' => $leaflet->link_url,
                'image_url' => $leaflet->image_path === null ? null : Storage::disk('public')->url($leaflet->image_path),
            ],
        ]);
    }

    public function update(UpdateLeafletRequest $request, Leaflet $leaflet): RedirectResponse
    {
        $linkUrl = $request->validated('link_url') ?: null;
        $linkChanged = $linkUrl !== $leaflet->link_url;

        $leaflet->update([
            ...$request->safe()->only(['body', 'visibility']),
            'link_url' => $linkUrl,
        ]);

        if ($request->boolean('remove_image') || $request->file('image') !== null || $linkUrl !== null) {
            $this->removeImage($leaflet);
        }

        $this->storeImage($leaflet, $request->file('image'));

        if ($linkChanged) {
            $this->refreshLinkPreview($leaflet);
        }

        return redirect()->route('leaflets.index');
    }

    public function destroy(Request $request, Leaflet $leaflet): RedirectResponse
    {
        Gate::authorize('delete', $leaflet);

        $this->removeImage($leaflet);

        $leaflet->reports()->where('status', ReportStatus::Pending)->update([
            'status' => ReportStatus::Resolved,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $leaflet->delete();

        return redirect()->route('leaflets.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Leaflet $leaflet, User $viewer): array
    {
        return [
            'id' => $leaflet->id,
            'body' => $leaflet->body,
            'visibility' => $leaflet->visibility->value,
            'created_at' => $leaflet->created_at->toIso8601String(),
            'is_edited' => $leaflet->updated_at->gt($leaflet->created_at->addMinute()),
            'owner' => ['id' => $leaflet->owner->id, 'name' => $leaflet->owner->name, 'username' => $leaflet->owner->username, 'avatar_url' => $leaflet->owner->avatar_url],
            'image_url' => $leaflet->image_path === null ? null : Storage::disk('public')->url($leaflet->image_path),
            'link' => $leaflet->link_url === null ? null : [
                'url' => $leaflet->link_url,
                'host' => parse_url($leaflet->link_url, PHP_URL_HOST),
                'title' => $leaflet->link_title,
                'description' => $leaflet->link_description,
                'image_url' => $leaflet->link_image_url,
            ],
            'can_edit' => Gate::forUser($viewer)->allows('update', $leaflet),
            'can_delete' => Gate::forUser($viewer)->allows('delete', $leaflet),
            'can_report' => $leaflet->user_id !== $viewer->id,
            'reactions' => Reaction::summarize($leaflet->reactions, $viewer),
            'responses_count' => (int) $leaflet->responses_count,
            // The thread itself only on the leaflet's own page; the feed
            // shows how many there are.
            'responses' => ! $leaflet->relationLoaded('responses') ? null : $leaflet->responses->map(fn (LeafletResponse $response) => [
                'id' => $response->id,
                'body' => $response->body,
                'created_at' => $response->created_at->toIso8601String(),
                'user' => ['id' => $response->user->id, 'name' => $response->user->name, 'username' => $response->user->username, 'avatar_url' => $response->user->avatar_url],
                'can_delete' => $viewer->id === $response->user_id || $viewer->id === $leaflet->user_id || $viewer->isStaff(),
                'can_report' => $response->user_id !== $viewer->id,
                'reactions' => Reaction::summarize($response->reactions, $viewer),
            ])->values()->all(),
        ];
    }

    private function storeImage(Leaflet $leaflet, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        $this->removeImage($leaflet);

        $leaflet->update([
            'image_path' => $this->imageProcessor->store($file, "leaflets/{$leaflet->id}", ImageProcessor::EVENT_MAX_EDGE),
        ]);
    }

    private function removeImage(Leaflet $leaflet): void
    {
        if ($leaflet->image_path === null) {
            return;
        }

        Storage::disk('public')->delete($leaflet->image_path);
        $leaflet->update(['image_path' => null]);
    }

    /**
     * A link whose preview was never fetched (the job was lost, say, to a
     * queue with no worker) gets another attempt when it is shown, at most
     * once a minute so a stubborn one doesn't refetch on every poll.
     */
    private function retryMissingLinkPreview(Leaflet $leaflet): void
    {
        if ($leaflet->link_url === null || $leaflet->link_fetched_at !== null || $leaflet->updated_at->gt(now()->subMinute())) {
            return;
        }

        $leaflet->touch();

        FetchLeafletLinkPreview::dispatchAfterResponse($leaflet);
    }

    private function refreshLinkPreview(Leaflet $leaflet): void
    {
        $leaflet->update(['link_title' => null, 'link_description' => null, 'link_image_url' => null, 'link_fetched_at' => null]);

        // Runs after the response is sent, whatever the queue connection
        // is, so a preview never depends on a worker being present.
        if ($leaflet->link_url !== null) {
            FetchLeafletLinkPreview::dispatchAfterResponse($leaflet);
        }
    }
}
