<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'PUBLISHED')->where('published_at', '<=', now());
    }
}
