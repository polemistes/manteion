<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVisibility;
use App\Enums\AttendeeSource;
use Database\Factories\EventAttendeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property int|null $invited_by_user_id
 * @property AttendanceStatus $status
 * @property AttendanceVisibility $attendance_visibility
 * @property AttendeeSource $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'user_id', 'invited_by_user_id', 'status', 'attendance_visibility', 'source'])]
class EventAttendee extends Model
{
    /** @use HasFactory<EventAttendeeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'attendance_visibility' => AttendanceVisibility::class,
            'source' => AttendeeSource::class,
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Whether this attendance may be shown to the viewer at all, given the
     * attendee's visibility choice. The event owner and the attendee
     * themselves are handled by the caller; this is the choice as it
     * applies to everyone else.
     *
     * @param  Collection<int, int>  $viewerFriendIds
     */
    public function isVisibleGiven(Collection $viewerFriendIds): bool
    {
        return match ($this->attendance_visibility) {
            AttendanceVisibility::Everyone => true,
            AttendanceVisibility::Friends => $viewerFriendIds->contains($this->user_id),
            AttendanceVisibility::None => false,
        };
    }
}
