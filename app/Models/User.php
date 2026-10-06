<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AdminAlert;
use App\Enums\FollowStatus;
use App\Enums\FriendshipStatus;
use App\Enums\NotificationCategory;
use App\Enums\ThemeMode;
use App\Enums\UserRole;
use App\Notifications\PushTest;
use App\Services\ActivityFeed;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use NotificationChannels\WebPush\HasPushSubscriptions;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property bool $allow_public_follow
 * @property bool $show_friends_list
 * @property bool $appear_in_friends_lists
 * @property bool $share_typing
 * @property bool $share_read_receipts
 * @property bool $searchable
 * @property array<string, mixed>|null $notification_settings
 * @property string|null $avatar_path
 * @property string|null $bio
 * @property string|null $locale
 * @property ThemeMode $theme_mode
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_reason
 * @property int|null $suspended_by
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar_url
 */
#[Fillable(['name', 'username', 'email', 'password', 'role', 'allow_public_follow', 'show_friends_list', 'appear_in_friends_lists', 'searchable', 'share_typing', 'share_read_receipts', 'notification_settings', 'avatar_path', 'bio', 'locale', 'theme_mode', 'suspended_at', 'suspended_reason', 'suspended_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

    /**
     * Push subscriptions (each device's endpoint, keys and user agent) and
     * stored notifications hang off polymorphic relations, which no foreign
     * key cascades, so they go when the account goes. So does what the
     * account left in other people's Activity: what they did (a comment
     * quoted, an invitation) and anything about their events and leaflets,
     * which the database cascades away with them.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $user->pushSubscriptions()->delete();
            $user->notifications()->delete();

            ActivityFeed::withdrawBy($user);
            $user->events()->pluck('id')->each(fn (int $id) => ActivityFeed::withdrawAbout('event', $id));
            $user->leaflets()->pluck('id')->each(fn (int $id) => ActivityFeed::withdrawAbout('leaflet', $id));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'allow_public_follow' => 'boolean',
            'show_friends_list' => 'boolean',
            'appear_in_friends_lists' => 'boolean',
            'share_typing' => 'boolean',
            'share_read_receipts' => 'boolean',
            'searchable' => 'boolean',
            'notification_settings' => 'array',
            'suspended_at' => 'datetime',
            'theme_mode' => ThemeMode::class,
        ];
    }

    public function isModerator(): bool
    {
        return $this->role === UserRole::Moderator;
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isStaff(): bool
    {
        return $this->isModerator() || $this->isAdministrator();
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * The user's effective notification settings: the push setting of every
     * category and the message-preview choice, with the defaults filled in
     * for whatever the user has not stored.
     *
     * @return array{push: array<string, bool>, message_previews: bool, admin_email: array<string, bool>}
     */
    public function notificationSettings(): array
    {
        $stored = $this->notification_settings ?? [];
        $storedPush = is_array($stored['push'] ?? null) ? $stored['push'] : [];

        $push = [];

        foreach (NotificationCategory::cases() as $category) {
            $push[$category->value] = (bool) ($storedPush[$category->value] ?? $category->pushDefault());
        }

        $storedAdmin = is_array($stored['admin_email'] ?? null) ? $stored['admin_email'] : [];
        $adminEmail = [];

        foreach (AdminAlert::cases() as $kind) {
            $adminEmail[$kind->value] = (bool) ($storedAdmin[$kind->value] ?? false);
        }

        return [
            'push' => $push,
            'message_previews' => (bool) ($stored['message_previews'] ?? true),
            // Only acted on for administrators (see wantsAdminEmail).
            'admin_email' => $adminEmail,
        ];
    }

    /**
     * Whether this administrator asked to be emailed about this kind of
     * thing (App\Services\AdminAlerts). Never true for anyone else.
     */
    public function wantsAdminEmail(AdminAlert $kind): bool
    {
        return $this->role === UserRole::Administrator
            && $this->notificationSettings()['admin_email'][$kind->value];
    }

    /**
     * Whether this category should reach the user's devices as a push
     * notification. Each subscribed device gets it while this is on.
     */
    public function wantsPush(NotificationCategory $category): bool
    {
        return $this->notificationSettings()['push'][$category->value];
    }

    /**
     * The devices a push goes to: every one of the user's, except for a test
     * push, which goes only to the device that asked for it.
     *
     * @return EloquentCollection<int, WebPushSubscription>
     */
    public function routeNotificationForWebPush(?Notification $notification = null): EloquentCollection
    {
        if ($notification instanceof PushTest) {
            return $this->pushSubscriptions()->where('endpoint', $notification->endpoint)->get();
        }

        return $this->pushSubscriptions;
    }

    /**
     * Whether message pushes may show the message text, rather than only
     * that a new message arrived.
     */
    public function showsMessagePreviews(): bool
    {
        return $this->notificationSettings()['message_previews'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        );
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function friendshipsAsRequester(): HasMany
    {
        return $this->hasMany(Friendship::class, 'requester_id');
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function friendshipsAsAddressee(): HasMany
    {
        return $this->hasMany(Friendship::class, 'addressee_id');
    }

    /**
     * Requests this user sent that have not been accepted. A declined
     * request is deliberately included: the addressee's decision is never
     * revealed to the requester, who keeps seeing the request as open.
     *
     * @return HasMany<Friendship, $this>
     */
    public function sentFriendRequests(): HasMany
    {
        return $this->friendshipsAsRequester()->whereIn('status', [FriendshipStatus::Pending, FriendshipStatus::Declined]);
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function pendingFriendRequests(): HasMany
    {
        return $this->friendshipsAsAddressee()->pending();
    }

    /**
     * Ids of every accepted friend, in one query, whichever side of the
     * request this user was on.
     *
     * @return Collection<int, int>
     */
    public function friendIds(): Collection
    {
        return Friendship::query()
            ->accepted()
            ->where(fn ($query) => $query->where('requester_id', $this->id)->orWhere('addressee_id', $this->id))
            ->get(['requester_id', 'addressee_id'])
            ->map(fn (Friendship $friendship) => $friendship->requester_id === $this->id
                ? $friendship->addressee_id
                : $friendship->requester_id)
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function friends(): Collection
    {
        return User::query()->whereIn('id', $this->friendIds())->orderBy('name')->get();
    }

    public function isFriendsWith(User $user): bool
    {
        return Friendship::query()
            ->accepted()
            ->where(fn ($query) => $query->where('requester_id', $this->id)->where('addressee_id', $user->id))
            ->orWhere(fn ($query) => $query->where('requester_id', $user->id)->where('addressee_id', $this->id))
            ->exists();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')
            ->withPivot('id', 'status')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')
            ->withPivot('id', 'status')
            ->withTimestamps();
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->wherePivot('status', FollowStatus::Accepted)->whereKey($user->id)->exists();
    }

    /**
     * @return HasMany<ContactGroup, $this>
     */
    public function contactGroups(): HasMany
    {
        return $this->hasMany(ContactGroup::class);
    }

    /**
     * The organizers this person speaks for (accepted), newest first.
     *
     * @return BelongsToMany<Organizer, $this, OrganizerSpokesperson>
     */
    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class, 'organizer_spokespersons')
            ->using(OrganizerSpokesperson::class)
            ->withPivot(['id', 'role', 'listed', 'invited_by_user_id', 'accepted_at'])
            ->wherePivotNotNull('accepted_at')
            ->withTimestamps();
    }

    /**
     * Invitations to speak for an organizer, not yet answered.
     *
     * @return HasMany<OrganizerSpokesperson, $this>
     */
    public function spokespersonInvitations(): HasMany
    {
        return $this->hasMany(OrganizerSpokesperson::class)->whereNull('accepted_at');
    }

    /**
     * @return BelongsToMany<Organizer, $this>
     */
    public function followedOrganizers(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class, 'organizer_follows')->withTimestamps();
    }

    /** @var Collection<int, int>|null */
    private ?Collection $organizerIds = null;

    /** @var Collection<int, int>|null */
    private ?Collection $followedOrganizerIds = null;

    /**
     * The ids of the organizers this person speaks for, read once per
     * request: lists check it for every event.
     *
     * @return Collection<int, int>
     */
    public function organizerIds(): Collection
    {
        return $this->organizerIds ??= $this->organizers()->pluck('organizers.id');
    }

    /**
     * @return Collection<int, int>
     */
    public function followedOrganizerIds(): Collection
    {
        return $this->followedOrganizerIds ??= $this->followedOrganizers()->pluck('organizers.id');
    }

    /**
     * After a change to what this person speaks for or follows.
     */
    public function forgetOrganizerIds(): void
    {
        $this->organizerIds = null;
        $this->followedOrganizerIds = null;
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Leaflet, $this>
     */
    public function leaflets(): HasMany
    {
        return $this->hasMany(Leaflet::class);
    }

    /**
     * @return HasMany<EventAttendee, $this>
     */
    public function eventAttendances(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /**
     * @return HasMany<ContactTagPreference, $this>
     */
    public function contactTagPreferences(): HasMany
    {
        return $this->hasMany(ContactTagPreference::class);
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function conversationParticipations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }
}
