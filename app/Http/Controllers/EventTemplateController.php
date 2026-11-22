<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DecidesEventTimeZone;
use App\Http\Controllers\Concerns\ServesPrivateImages;
use App\Http\Requests\StoreEventTemplateRequest;
use App\Http\Requests\UpdateEventTemplateRequest;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Organizer;
use App\Models\Tag;
use App\Services\EventStartingPoints;
use App\Services\ImageProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventTemplateController extends Controller
{
    use DecidesEventTimeZone;
    use ServesPrivateImages;

    public const EVENTS_PER_PAGE = 20;

    public function __construct(private ImageProcessor $imageProcessor, private EventStartingPoints $startingPoints) {}

    /**
     * The templates the user may use, and the events they host (newest
     * first), each of which can become a template or start a new event.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $events = Event::query()
            ->where(fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->orWhereIn('organizer_id', $user->organizerIds()))
            ->where('is_deleted', false)
            ->with('organizer')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(self::EVENTS_PER_PAGE)
            ->withQueryString();

        return Inertia::render('events/Templates', [
            'templates' => EventTemplate::query()->usableBy($user)->with('organizer')->orderBy('name')->orderBy('id')->get()
                ->map(fn (EventTemplate $template) => $template->summary())->values()->all(),
            'events' => $events->getCollection()->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'starts_at' => $event->starts_at->toIso8601String(),
                'place' => $event->place,
                'city' => $event->city,
                'organizer' => $event->organizer?->summary(),
            ])->values()->all(),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
                'previous_url' => $events->previousPageUrl(),
                'next_url' => $events->nextPageUrl(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', EventTemplate::class);

        return Inertia::render('events/TemplateCreate', [
            'categories' => Tag::categoriesTree(),
            // The organizers the template may be made for.
            'organizers' => $request->user()->organizers()->get()->map(fn (Organizer $organizer) => $organizer->summary())->values()->all(),
        ]);
    }

    public function store(StoreEventTemplateRequest $request): RedirectResponse
    {
        // For one of the user's organizers (and then shared by its
        // spokespersons), or their own.
        $organizerId = $request->validated('organizer_id');
        abort_if($organizerId !== null && ! $request->user()->organizerIds()->contains((int) $organizerId), 403);

        $template = EventTemplate::query()->create([
            'user_id' => $request->user()->id,
            'organizer_id' => $organizerId === null ? null : (int) $organizerId,
            ...$this->attributesFrom($request, null),
        ]);

        $template->tags()->sync(EventStartingPoints::tagIdsWithParents($request->input('tag_ids', [])));
        $this->storeCover($template, $request);

        return redirect()->route('event-templates.index');
    }

    public function edit(EventTemplate $eventTemplate): Response
    {
        Gate::authorize('update', $eventTemplate);

        $eventTemplate->load(['tags', 'organizer']);

        return Inertia::render('events/TemplateEdit', [
            'template' => [
                'id' => $eventTemplate->id,
                ...$this->startingPoints->formValues($eventTemplate),
                'starts_time' => $eventTemplate->starts_time,
                'ends_time' => $eventTemplate->ends_time,
                'ends_days_later' => $eventTemplate->ends_days_later,
                'tag_ids' => $eventTemplate->tags->pluck('id')->values(),
                'image' => $eventTemplate->cover(),
                'organizer' => $eventTemplate->organizer?->summary(),
            ],
            'categories' => Tag::categoriesTree(),
        ]);
    }

    public function update(UpdateEventTemplateRequest $request, EventTemplate $eventTemplate): RedirectResponse
    {
        $eventTemplate->update($this->attributesFrom($request, $eventTemplate));

        $eventTemplate->tags()->sync(EventStartingPoints::tagIdsWithParents($request->input('tag_ids', [])));
        $this->storeCover($eventTemplate, $request);

        return redirect()->route('event-templates.index');
    }

    public function destroy(EventTemplate $eventTemplate): RedirectResponse
    {
        Gate::authorize('delete', $eventTemplate);

        $eventTemplate->delete();

        return redirect()->route('event-templates.index');
    }

    /**
     * A template's cover, for whoever may use the template. The address
     * names the file, so a replaced cover is never mistaken for the old
     * one.
     */
    public function cover(EventTemplate $eventTemplate, string $file): StreamedResponse
    {
        Gate::authorize('view', $eventTemplate);

        abort_unless($eventTemplate->image_path !== null && basename($eventTemplate->image_path) === $file, 404);

        return $this->privateImage($eventTemplate->image_path);
    }

    /**
     * What the form sent, as the template keeps it. An end time is kept
     * only with a start, and always falls after it: one that is not later
     * on the clock belongs to the next day at the earliest.
     *
     * @return array<string, mixed>
     */
    private function attributesFrom(StoreEventTemplateRequest|UpdateEventTemplateRequest $request, ?EventTemplate $template): array
    {
        $startsTime = $request->validated('starts_time');
        $endsTime = $startsTime === null ? null : $request->validated('ends_time');
        $daysLater = $endsTime === null ? 0 : (int) $request->validated('ends_days_later');

        if ($endsTime !== null && $daysLater === 0 && $endsTime <= $startsTime) {
            $daysLater = 1;
        }

        return [
            ...$request->safe()->only(['name', 'place', 'latitude', 'longitude', 'city', 'country', 'country_code', 'visibility', 'allow_invitee_invites', 'allow_invitee_comments']),
            'description' => $request->validated('description') ?? '',
            'starts_time' => $startsTime,
            'ends_time' => $endsTime,
            'ends_days_later' => $daysLater,
            ...$this->timeZoneFrom($request, $template?->timezone_mode, $template?->localTimeZone()),
        ];
    }

    /**
     * Replaces the cover with an upload; without one, removes it when the
     * form says so, or applies a changed "fill the frame / show it whole"
     * choice to the cover there is.
     */
    private function storeCover(EventTemplate $template, StoreEventTemplateRequest|UpdateEventTemplateRequest $request): void
    {
        $file = $request->file('image');
        $fit = in_array($request->input('image_fit'), ['cover', 'contain'], true) ? $request->input('image_fit') : null;

        if ($file !== null) {
            $template->deleteCoverFile();

            $stored = $this->imageProcessor->storeWithSize($file, 'event-templates', ImageProcessor::EVENT_MAX_EDGE, ImageProcessor::PRIVATE_DISK);

            $template->update([
                'image_path' => $stored['path'],
                'image_width' => $stored['width'],
                'image_height' => $stored['height'],
                'image_fit' => $fit,
            ]);

            return;
        }

        if ($request->boolean('remove_image')) {
            $template->deleteCoverFile();
            $template->update(['image_path' => null, 'image_width' => null, 'image_height' => null, 'image_fit' => null]);

            return;
        }

        if ($template->image_path !== null && $request->exists('image_fit')) {
            $template->update(['image_fit' => $fit]);
        }
    }
}
