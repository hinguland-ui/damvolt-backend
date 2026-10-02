<?php

namespace App\Models;

use App\Models\Concerns\ClearsContentCache;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use ClearsContentCache;

    protected $guarded = [];

    private static ?array $memo = null;

    /** All sections keyed by name, decoded once per request. */
    public static function everything(): array
    {
        return self::$memo ??= self::query()->pluck('value', 'key')
            ->map(fn ($v) => json_decode($v, true) ?: [])
            ->all();
    }

    /** The decoded sections are remembered for the request; call this to start fresh (tests, long-running workers). */
    public static function forgetMemo(): void
    {
        self::$memo = null;
    }

    public static function section(string $key, array $default = []): array
    {
        return array_replace($default, self::everything()[$key] ?? []);
    }

    public static function put(string $key, array $data): void
    {
        self::updateOrCreate(['key' => $key], ['value' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        self::$memo = null;
    }
}
