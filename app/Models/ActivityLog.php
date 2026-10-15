<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

#[Fillable(['user_id', 'action', 'subject', 'area', 'ip_address'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Write one line to the activity log.
     */
    public static function record(string $action, ?User $user, ?Request $request = null, ?string $subject = null, ?string $area = null): self
    {
        return static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject' => $subject !== null ? mb_substr($subject, 0, 255) : null,
            'area' => $area,
            'ip_address' => $request?->ip(),
        ]);
    }
}
