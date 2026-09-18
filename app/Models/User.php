<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FollowStatus;
use App\Enums\FriendshipStatus;
use App\Enums\ThemeMode;
use App\Enums\ThemeStyle;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use NotificationChannels\WebPush\HasPushSubscriptions;

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
 * @property bool $searchable
 * @property bool $push_notifications_enabled
 * @property bool $notify_event_invites
 * @property bool $notify_messages
 * @property bool $notify_friend_requests
 * @property bool $notify_follow_requests
 * @property string|null $avatar_path
 * @property string|null $bio
 * @property string|null $locale
 * @property ThemeStyle $theme_style
 * @property ThemeMode $theme_mode
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_reason
 * @property int|null $suspended_by
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar_url
 */
#[Fillable(['name', 'username', 'email', 'password', 'role', 'allow_public_follow', 'show_friends_list', 'appear_in_friends_lists', 'searchable', 'push_notifications_enabled', 'notify_event_invites', 'notify_messages', 'notify_friend_requests', 'notify_follow_requests', 'avatar_path', 'bio', 'locale', 'theme_style', 'theme_mode', 'suspended_at', 'suspended_reason', 'suspended_by'])]
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
            'searchable' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'notify_event_invites' => 'boolean',
            'notify_messages' => 'boolean',
            'notify_friend_requests' => 'boolean',
            'notify_follow_requests' => 'boolean',
            'suspended_at' => 'datetime',
            'theme_style' => ThemeStyle::class,
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
