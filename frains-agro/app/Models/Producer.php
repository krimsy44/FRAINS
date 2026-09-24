<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producer extends Model
{
    protected $guarded = ['id'];

    public function parcels()
    {
        return $this->hasMany(Parcel::class);
    }
}
