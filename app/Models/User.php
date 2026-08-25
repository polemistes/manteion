<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FollowStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $allow_public_follow
 * @property string|null $avatar_path
 * @property string|null $bio
 * @property string|null $locale
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar_url
 */
#[Fillable(['name', 'username', 'email', 'password', 'allow_public_follow', 'avatar_path', 'bio', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'allow_public_follow' => 'boolean',
        ];
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
     * @return HasMany<Friendship, $this>
     */
    public function sentFriendRequests(): HasMany
    {
        return $this->friendshipsAsRequester()->pending();
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function pendingFriendRequests(): HasMany
    {
        return $this->friendshipsAsAddressee()->pending();
    }

    /**
     * @return Collection<int, User>
     */
    public function friends(): Collection
    {
        $viaSentRequests = $this->friendshipsAsRequester()->accepted()->with('addressee')->get()->pluck('addressee');
        $viaReceivedRequests = $this->friendshipsAsAddressee()->accepted()->with('requester')->get()->pluck('requester');

        return $viaSentRequests->merge($viaReceivedRequests);
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
