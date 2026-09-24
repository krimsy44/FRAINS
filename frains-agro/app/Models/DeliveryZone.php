<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = ['name', 'description', 'base_fee', 'is_active'];

    protected function casts(): array
    {
        return ['base_fee' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
