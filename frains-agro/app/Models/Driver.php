<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
}
