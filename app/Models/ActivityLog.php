<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

#[Fillable(['user_id', 'action', 'subject', 'area', 'ip_address', 'details'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'details' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    /**
     * Write one line to the activity log.
     */
    public static function record(
        string $action,
        ?User $user,
        ?Request $request = null,
        ?string $subject = null,
        ?string $area = null,
        ?array $details = null,
    ): self {
        return static::create([
            'user_id' => $user?->id,
            'action' => mb_substr($action, 0, 40),
            'subject' => $subject !== null ? mb_substr($subject, 0, 255) : null,
            'area' => $area !== null ? mb_substr($area, 0, 50) : null,
            'ip_address' => $request?->ip(),
            'details' => $details,
        ]);
    }
}
