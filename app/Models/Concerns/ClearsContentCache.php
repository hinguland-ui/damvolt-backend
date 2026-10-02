<?php

namespace App\Models\Concerns;

use App\Support\Content;

// Any save/delete on a content model drops the cached /api/content payload.
trait ClearsContentCache
{
    protected static function bootClearsContentCache(): void
    {
        static::saved(fn () => Content::flush());
        static::deleted(fn () => Content::flush());
    }
}
