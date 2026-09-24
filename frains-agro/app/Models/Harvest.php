<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Harvest extends Model
{
    protected $guarded = ['id'];

    public function cultivation()
    {
        return $this->belongsTo(Cultivation::class);
    }
}
