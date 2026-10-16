<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'group'])]
class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->find($key);

        return $setting?->value ?? $default;
    }
}
