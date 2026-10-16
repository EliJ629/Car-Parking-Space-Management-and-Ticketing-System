<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One driver notification (N-xx) or one role alert for staff (S-xx).
 * Driver notices have a user_id. Role alerts have an audience_role and are
 * marked read for everyone in the role.
 */
#[Fillable([
    'user_id', 'audience_role', 'code', 'kind', 'title', 'body',
    'subject_type', 'subject_id', 'data', 'group_key', 'read_at',
])]
class Notice extends Model
{
    protected $table = 'bayline_notices';

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function scopeForDriver(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereNull('user_id')->where('audience_role', $role);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
