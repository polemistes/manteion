<?php

namespace App\Models;

use App\Enums\SpokespersonRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * One person's standing with one organizer (see the migration). Before
 * accepted_at is set it is an invitation awaiting an answer.
 *
 * @property int $id
 * @property int $organizer_id
 * @property int $user_id
 * @property SpokespersonRole $role
 * @property bool $listed
 * @property int|null $invited_by_user_id
 * @property Carbon|null $accepted_at
 */
class OrganizerSpokesperson extends Pivot
{
    public $incrementing = true;

    protected $table = 'organizer_spokespersons';

    protected function casts(): array
    {
        return [
            'role' => SpokespersonRole::class,
            'listed' => 'boolean',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organizer, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
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

    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }
}
