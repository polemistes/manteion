<?php

namespace App\Models;

use App\Enums\ContactGroupType;
use Database\Factories\ContactGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ContactGroupType $type
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'type', 'name'])]
class ContactGroup extends Model
{
    /** @use HasFactory<ContactGroupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ContactGroupType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contact_group_members', 'contact_group_id', 'member_user_id')
            ->withTimestamps();
    }

    /**
     * @param  Builder<ContactGroup>  $query
     * @return Builder<ContactGroup>
     */
    protected function scopeOfType(Builder $query, ContactGroupType $type): Builder
    {
        return $query->where('type', $type);
    }
}
