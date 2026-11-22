<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Enums\SpokespersonRole;
use App\Events\BadgesUpdated;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Leaflet;
use App\Models\Organizer;
use App\Models\OrganizerSpokesperson;
use App\Models\User;
use App\Notifications\OrganizerHandedOver;
use App\Notifications\SpokespersonInvited;
use Illuminate\Support\Collection;

/**
 * A spokesperson stops speaking for an organizer: by stepping down, by
 * being removed, or because their account is deleted. What they made for
 * it (events, leaflets, templates) is handed to the main spokesperson, or
 * deleted if they chose so; a main spokesperson who goes names who takes
 * over, and that person receives it all. An organizer whose only
 * spokesperson goes is deleted, since no one could act for it again.
 */
class OrganizerDepartures
{
    /**
     * What each of the organizers a person speaks for would need decided
     * if they left it: whether they are its main spokesperson, who else
     * speaks for it (longest-serving first), who would receive what they
     * made, and how much that is.
     *
     * @return list<array{id: int, name: string, is_main: bool, others: list<array{id: int, name: string}>, receiver: array{id: int, name: string}|null, made: array{events: int, leaflets: int, templates: int}}>
     */
    public function choicesFor(User $user): array
    {
        return array_values(
            $user->organizers()->orderBy('name')->get()
                ->map(fn (Organizer $organizer) => $this->choiceFor($organizer, $user))
                ->all()
        );
    }

    /**
     * @return array{id: int, name: string, is_main: bool, others: list<array{id: int, name: string}>, receiver: array{id: int, name: string}|null, made: array{events: int, leaflets: int, templates: int}}
     */
    public function choiceFor(Organizer $organizer, User $user): array
    {
        $others = $this->othersThan($organizer, $user);
        $isMain = $organizer->isMainSpokesperson($user);
        $receiver = $isMain ? null : $this->receiverAmong($organizer, $others);

        return [
            'id' => $organizer->id,
            'name' => $organizer->name,
            'is_main' => $isMain,
            'others' => array_values($others->map(fn (User $other) => ['id' => $other->id, 'name' => $other->name])->all()),
            'receiver' => $receiver === null ? null : ['id' => $receiver->id, 'name' => $receiver->name],
            'made' => $this->madeBy($organizer, $user),
        ];
    }

    /**
     * How much a person made for an organizer.
     *
     * @return array{events: int, leaflets: int, templates: int}
     */
    public function madeBy(Organizer $organizer, User $user): array
    {
        return [
            'events' => $organizer->events()->where('user_id', $user->id)->count(),
            'leaflets' => $organizer->leaflets()->where('user_id', $user->id)->count(),
            'templates' => $organizer->eventTemplates()->where('user_id', $user->id)->count(),
        ];
    }

    /**
     * Whether a main spokesperson can go: only with someone to take over.
     */
    public function hasOthers(Organizer $organizer, User $user): bool
    {
        return $this->othersThan($organizer, $user)->isNotEmpty();
    }

    /**
     * Whether `$successor` could take over from `$user`: another person
     * who speaks for the organizer and has accepted.
     */
    public function canSucceed(Organizer $organizer, User $user, int $successorId): bool
    {
        return $this->othersThan($organizer, $user)->contains('id', $successorId);
    }

    /**
     * The person leaves every organizer they speak for, as their account
     * is deleted. `$choices` holds what they decided for each, by the
     * organizer's id; an organizer without a choice is handed over, with
     * its longest-serving other spokesperson as the new main one where it
     * needs one.
     *
     * @param  array<int|string, array{hand_over?: mixed, successor_id?: mixed}>  $choices
     */
    public function leaveAll(User $user, array $choices = []): void
    {
        foreach ($user->organizers()->get() as $organizer) {
            $choice = $choices[$organizer->id] ?? [];
            $successorId = $choice['successor_id'] ?? null;

            $this->leave(
                $organizer,
                $user,
                handOver: filter_var($choice['hand_over'] ?? true, FILTER_VALIDATE_BOOL),
                successor: $successorId === null ? null : User::query()->find((int) $successorId),
                accountGoes: true,
            );
        }
    }

    /**
     * One person stops speaking for one organizer.
     *
     * A main spokesperson is followed by `$successor` (the longest-serving
     * other one when none is named); without anyone else the organizer is
     * deleted. What the person made goes to the main spokesperson (the new
     * one, when they were it) or is deleted, as `$handOver` says. Whoever
     * receives something, or the main role, is told in Activity (not when
     * they did it themself by removing the person: `$tellReceiver`); the
     * notice names the person unless their account goes (`$accountGoes`),
     * since nothing of a deleted account stays in other people's Activity.
     */
    public function leave(Organizer $organizer, User $user, bool $handOver = true, ?User $successor = null, bool $accountGoes = false, bool $tellReceiver = true): void
    {
        $membership = $organizer->membershipOf($user);

        if ($membership === null) {
            return;
        }

        if ($membership->isPending()) {
            ActivityFeed::withdraw($user, SpokespersonInvited::class, 'spokesperson_id', $membership->id);
            $this->forget($membership, $user, $accountGoes);

            return;
        }

        $others = $this->othersThan($organizer, $user);
        $wasMain = $membership->role === SpokespersonRole::Main;

        // The last one out: the organizer cannot go on without anyone.
        if ($others->isEmpty()) {
            $this->deleteOrganizer($organizer);
            $user->forgetOrganizerIds();

            return;
        }

        // The receiver is the main spokesperson: the one taking over from
        // this person, the one there is, or (an organizer left without
        // one) the longest-serving, who becomes it.
        $receiver = $wasMain
            ? ($others->firstWhere('id', $successor?->id) ?? $others->first())
            : $this->receiverAmong($organizer, $others);
        $becomesMain = ! $organizer->isMainSpokesperson($receiver);

        if ($becomesMain) {
            $organizer->memberships()->where('user_id', $receiver->id)->update(['role' => SpokespersonRole::Main]);
        }

        $made = $this->madeBy($organizer, $user);

        if ($handOver) {
            $organizer->events()->where('user_id', $user->id)->update(['user_id' => $receiver->id]);
            $organizer->leaflets()->where('user_id', $user->id)->update(['user_id' => $receiver->id]);
            $organizer->eventTemplates()->where('user_id', $user->id)->update(['user_id' => $receiver->id]);
        } else {
            $this->deleteWorkOf($organizer, $user);
            $made = ['events' => 0, 'leaflets' => 0, 'templates' => 0];
        }

        $this->forget($membership, $user, $accountGoes);

        if ($tellReceiver && ($becomesMain || array_sum($made) > 0)) {
            $receiver->notify(
                (new OrganizerHandedOver($organizer, $accountGoes ? null : $user, $becomesMain, $made))->locale($receiver->locale ?? 'en')
            );
            BadgesUpdated::dispatch($receiver->id);
        }
    }

    /**
     * Deletes an organizer with all it has: its events and leaflets (the
     * database cascades), their pictures and its own, and everyone's
     * Activity about them.
     */
    public function deleteOrganizer(Organizer $organizer): void
    {
        $organizer->events()->pluck('id')->each(fn (int $id) => ActivityFeed::withdrawAbout('event', $id));
        $organizer->leaflets()->pluck('id')->each(fn (int $id) => ActivityFeed::withdrawAbout('leaflet', $id));
        ActivityFeed::withdrawAbout('organizer', $organizer->id);

        $organizer->deleteUploadedFiles();
        $organizer->delete();
    }

    /**
     * Deletes what a person made for an organizer, files and Activity
     * included.
     */
    private function deleteWorkOf(Organizer $organizer, User $user): void
    {
        $organizer->events()->where('user_id', $user->id)->with('images')->get()
            ->each(fn (Event $event) => $event->deleteForGood());

        $organizer->leaflets()->where('user_id', $user->id)->get()->each(function (Leaflet $leaflet): void {
            $leaflet->reports()->where('status', ReportStatus::Pending)->update([
                'status' => ReportStatus::Resolved,
                'resolved_at' => now(),
            ]);

            $leaflet->delete();

            ActivityFeed::withdrawAbout('leaflet', $leaflet->id);
        });

        $organizer->eventTemplates()->where('user_id', $user->id)->get()
            ->each(fn (EventTemplate $template) => $template->delete());
    }

    /**
     * The membership goes; a person who stays is no longer counted as a
     * spokesperson anywhere in this request, and their badges are redrawn.
     */
    private function forget(OrganizerSpokesperson $membership, User $user, bool $accountGoes): void
    {
        $membership->delete();
        $user->forgetOrganizerIds();

        if (! $accountGoes) {
            BadgesUpdated::dispatch($user->id);
        }
    }

    /**
     * The others who speak for the organizer and have accepted, the
     * longest-serving first.
     *
     * @return Collection<int, User>
     */
    private function othersThan(Organizer $organizer, User $user): Collection
    {
        return $organizer->activeSpokespersons()
            ->where('users.id', '!=', $user->id)
            ->orderBy('organizer_spokespersons.accepted_at')
            ->orderBy('organizer_spokespersons.id')
            ->get();
    }

    /**
     * Who among them receives what a leaving spokesperson made: the main
     * one, else the longest-serving.
     *
     * @param  Collection<int, User>  $others
     */
    private function receiverAmong(Organizer $organizer, Collection $others): ?User
    {
        $main = $organizer->mainSpokesperson();

        return ($main === null ? null : $others->firstWhere('id', $main->id)) ?? $others->first();
    }
}
