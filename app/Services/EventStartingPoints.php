<?php

namespace App\Services;

use App\Enums\EventTimeZoneMode;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * What a new event can start from: a template, or an earlier event of the
 * host's. Either fills the new-event form with everything but the day;
 * the times come along as clock times ("19:00"), which the form puts on
 * the day the host picks. Covers are copied as files, never re-encoded and
 * never shared: every event and template owns its own.
 */
class EventStartingPoints
{
    /**
     * An event's times as clock times where it happens: when it starts,
     * when it ends, and how many days after the start that is.
     *
     * @return array{starts_time: string, ends_time: string|null, ends_days_later: int}
     */
    public function clockTimesOf(Event $event): array
    {
        $zone = $event->localTimeZone();
        $start = CarbonImmutable::instance($event->starts_at)->setTimezone($zone);
        $end = $event->ends_at === null ? null : CarbonImmutable::instance($event->ends_at)->setTimezone($zone);

        return [
            'starts_time' => $start->format('H:i'),
            'ends_time' => $end?->format('H:i'),
            // Counted between calendar days, so a night the clocks change
            // on is still one day.
            'ends_days_later' => $end === null
                ? 0
                : min(EventTemplate::MAX_DAYS_LATER, (int) CarbonImmutable::parse($start->toDateString(), 'UTC')
                    ->diffInDays(CarbonImmutable::parse($end->toDateString(), 'UTC'))),
        ];
    }

    /**
     * Makes a template of an event: all the host filled in, the clock
     * times, the categories and a copy of the cover. It belongs to the
     * organizer hosting the event, if one does.
     */
    public function templateFromEvent(Event $event, User $maker): EventTemplate
    {
        $event->loadMissing(['tags', 'images']);
        $image = $event->images->first();
        $coverPath = $image === null ? null : $this->copyCover($image->path, 'event-templates');

        try {
            return DB::transaction(function () use ($event, $maker, $image, $coverPath) {
                $template = EventTemplate::query()->create([
                    'user_id' => $maker->id,
                    'organizer_id' => $event->organizer_id,
                    ...$event->only(['name', 'place', 'latitude', 'longitude', 'city', 'country', 'country_code', 'description', 'timezone', 'timezone_mode', 'allow_invitee_invites', 'allow_invitee_comments']),
                    ...$this->clockTimesOf($event),
                    // Being searchable is bought for one event at a time.
                    'visibility' => $event->visibility === EventVisibility::Private ? EventVisibility::Private : EventVisibility::Public,
                    'image_path' => $coverPath,
                    'image_width' => $coverPath === null ? null : $image->width,
                    'image_height' => $coverPath === null ? null : $image->height,
                    'image_fit' => $coverPath === null ? null : $image->fit,
                ]);

                $template->tags()->sync($event->tags->pluck('id'));

                return $template;
            });
        } catch (Throwable $exception) {
            if ($coverPath !== null) {
                Storage::disk(ImageProcessor::PRIVATE_DISK)->delete($coverPath);
            }

            throw $exception;
        }
    }

    /**
     * A template as the start of a new event.
     *
     * @return array{source: array{type: 'template'|'event', id: int, name: string}, cover_from: string|null, starts_time: string|null, ends_time: string|null, ends_days_later: int, values: array<string, mixed>}
     */
    public function fromTemplate(EventTemplate $template): array
    {
        $template->loadMissing('tags');
        $cover = $template->cover();

        return [
            'source' => ['type' => 'template', 'id' => $template->id, 'name' => $template->name],
            'cover_from' => $cover === null ? null : "template:{$template->id}",
            'starts_time' => $template->starts_time,
            'ends_time' => $template->starts_time === null ? null : $template->ends_time,
            'ends_days_later' => $template->ends_days_later,
            'values' => [
                ...$this->formValues($template),
                'tag_ids' => $template->tags->pluck('id')->values()->all(),
                'image' => $cover,
            ],
        ];
    }

    /**
     * An earlier event as the start of a new one.
     *
     * @return array{source: array{type: 'template'|'event', id: int, name: string}, cover_from: string|null, starts_time: string|null, ends_time: string|null, ends_days_later: int, values: array<string, mixed>}
     */
    public function fromEvent(Event $event): array
    {
        $event->loadMissing(['tags', 'images']);
        $cover = $event->images->first()?->presentation();

        return [
            'source' => ['type' => 'event', 'id' => $event->id, 'name' => $event->name],
            'cover_from' => $cover === null ? null : "event:{$event->id}",
            ...$this->clockTimesOf($event),
            'values' => [
                ...$this->formValues($event),
                'tag_ids' => $event->tags->pluck('id')->values()->all(),
                'image' => $cover,
            ],
        ];
    }

    /**
     * The fields of the event form that an event and a template share.
     * The zone is only passed on where it was decided by something that
     * still holds (the pin, or the host's own choice): without either,
     * the form works on the clock of whoever fills it in.
     *
     * @return array<string, mixed>
     */
    public function formValues(Event|EventTemplate $from): array
    {
        $keepsZone = $from->timezone_mode === EventTimeZoneMode::Chosen
            || ($from->timezone_mode === EventTimeZoneMode::Automatic && $from->latitude !== null);

        return [
            'name' => $from->name,
            'organizer_id' => $from->organizer_id,
            'place' => $from->place,
            'latitude' => $from->latitude,
            'longitude' => $from->longitude,
            'city' => $from->city,
            'country' => $from->country,
            'country_code' => $from->country_code === null ? null : strtoupper($from->country_code),
            'description' => $from->description,
            'timezone' => $keepsZone ? $from->localTimeZone() : null,
            'timezone_mode' => $from->timezone_mode->value,
            'visibility' => $from->visibility === EventVisibility::Private ? 'private' : 'public',
            'allow_invitee_invites' => $from->allow_invitee_invites,
            'allow_invitee_comments' => $from->allow_invitee_comments,
        ];
    }

    /**
     * The cover a new event asked to start with (`template:5`, `event:12`),
     * for someone who may use that template or hosts that event; null when
     * it has none (any more). Anyone else is refused: the picture is
     * private.
     *
     * @return array{path: string, width: int|null, height: int|null, fit: string|null}|null
     */
    public function coverNamed(string $coverFrom, User $user): ?array
    {
        [$type, $id] = explode(':', $coverFrom, 2) + [null, null];

        if ($type === 'template') {
            $template = EventTemplate::query()->find((int) $id);
            abort_if($template !== null && ! $template->isUsableBy($user), 403);

            return $template?->image_path === null ? null : [
                'path' => $template->image_path,
                'width' => $template->image_width,
                'height' => $template->image_height,
                'fit' => $template->image_fit,
            ];
        }

        $event = Event::query()->with('images')->find((int) $id);
        abort_if($event !== null && ! $event->isHostedBy($user), 403);
        $image = $event?->images->first();

        return $image === null ? null : [
            'path' => $image->path,
            'width' => $image->width,
            'height' => $image->height,
            'fit' => $image->fit,
        ];
    }

    /**
     * Copies a cover into `$directory` on the private disk under a new
     * name, and says where; null when the file is not there to copy.
     */
    public function copyCover(string $path, string $directory): ?string
    {
        $disk = Storage::disk(ImageProcessor::PRIVATE_DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        $copy = trim($directory, '/').'/'.Str::random(40).'.jpg';
        $disk->copy($path, $copy);

        return $copy;
    }

    /**
     * The category ids to keep for a set chosen in the form: each one and
     * the parent of each subcategory.
     *
     * @param  array<int|string>  $tagIds
     * @return list<int>
     */
    public static function tagIdsWithParents(array $tagIds): array
    {
        $chosen = array_map(fn (int|string $id): int => (int) $id, $tagIds);

        $parents = Tag::query()->whereIn('id', $chosen)->whereNotNull('parent_id')->get(['id', 'parent_id'])
            ->map(fn (Tag $tag): int => (int) $tag->parent_id)
            ->all();

        return array_values(array_unique([...$chosen, ...$parents]));
    }
}
