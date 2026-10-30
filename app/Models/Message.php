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
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string $body
 * @property string|null $image_path
 * @property int|null $image_width
 * @property int|null $image_height
 * @property bool $is_deleted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['conversation_id', 'user_id', 'body', 'image_path', 'image_width', 'image_height', 'is_deleted'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * Where attached pictures are kept: the private disk, so a picture
     * has no address of its own that anyone could open. It is served by
     * MessageImageController, which checks who is asking.
     */
    public const IMAGE_DISK = 'local';

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
            'image_width' => 'integer',
            'image_height' => 'integer',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * The text anyone may still see: null once wiped by a delete (the
     * original text is never sent again), while a moderator's placeholder
     * is meant to be shown and passes through. A picture without words has
     * an empty string.
     */
    public function visibleBody(): ?string
    {
        return $this->is_deleted && $this->body === '' ? null : $this->body;
    }

    /**
     * The attached picture as the chat frames it: its address (which only
     * the conversation may open) and its size, so a thread can leave room
     * for it before it loads. Null without a picture, and once deleted.
     *
     * @return array{url: string, width: int|null, height: int|null}|null
     */
    public function imagePresentation(): ?array
    {
        if ($this->image_path === null) {
            return null;
        }

        return [
            'url' => route('messages.messages.image', [$this->conversation_id, $this->id]),
            'width' => $this->image_width,
            'height' => $this->image_height,
        ];
    }

    /**
     * Removes the picture's file. The columns are cleared by whoever
     * deletes the message, together with the rest of it.
     */
    public function deleteImageFile(): void
    {
        if ($this->image_path !== null) {
            Storage::disk(self::IMAGE_DISK)->delete($this->image_path);
        }
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
