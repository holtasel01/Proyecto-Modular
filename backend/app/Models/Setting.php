<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Lee un valor de configuración por clave, con caché en memoria de request
     * y un default de código por si la clave aún no existe en la tabla.
     */
    public static function get(string $key, string|int|float|null $default = null): string|int|float|null
    {
        $value = Cache::rememberForever("settings.$key", fn () => static::query()
            ->where('key', $key)
            ->value('value'));

        return $value ?? $default;
    }

    public static function set(string $key, string|int|float $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'updated_at' => now()],
        );

        Cache::forget("settings.$key");
    }
}
