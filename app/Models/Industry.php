<?php

namespace App\Models;

use App\Models\Concerns\ClearsContentCache;
use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    use ClearsContentCache;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeLive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
