<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    protected $guarded = ['id'];

    public function producer()
    {
        return $this->belongsTo(Producer::class);
    }

    public function cultivations()
    {
        return $this->hasMany(Cultivation::class);
    }
}
