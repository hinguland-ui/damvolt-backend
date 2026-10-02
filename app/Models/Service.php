<?php

namespace App\Models;

use App\Models\Concerns\ClearsContentCache;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use ClearsContentCache;

    protected $guarded = [];

    protected $casts = [
        'offerings' => 'array',
        'benefits' => 'array',
        'applications' => 'array',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function scopeLive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
