<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Creative'in anahtar-değer ayarları (JSON değer). Okuma/yazma MockupSettings üzerinden yapılır.
 */
class CreativeSetting extends Model
{
    protected $table = 'creative_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function read(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        return $row === null ? $default : ($row->value['v'] ?? $default);
    }

    public static function write(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
    }
}
