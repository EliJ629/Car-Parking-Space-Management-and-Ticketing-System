<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['incident_id', 'path', 'original_name', 'mime', 'size'])]
class IncidentPhoto extends Model
{
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
