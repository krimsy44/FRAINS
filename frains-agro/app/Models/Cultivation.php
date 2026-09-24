<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cultivation extends Model
{
    protected $guarded = ['id'];

    public function parcel()
    {
        return $this->belongsTo(Parcel::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function harvests()
    {
        return $this->hasMany(Harvest::class);
    }
}
