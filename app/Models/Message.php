<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_deleted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['conversation_id', 'user_id', 'body', 'is_deleted'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * As the column defaults, so a message just created (and broadcast
     * straight away) already says it is not deleted.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_deleted' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * The text anyone may still see: null once wiped by a delete (the
     * original text is never sent again), while a moderator's placeholder
     * is meant to be shown and passes through.
     */
    public function visibleBody(): ?string
    {
        return $this->is_deleted && $this->body === '' ? null : $this->body;
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The people who marked this message as seen, on purpose, oldest mark
     * first (see MessageSeenController).
     *
     * @return BelongsToMany<User, $this>
     */
    public function seenBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_seen_marks')
            ->withPivot('created_at')
            ->orderByPivot('created_at');
    }

    /**
     * @return MorphMany<Reaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
