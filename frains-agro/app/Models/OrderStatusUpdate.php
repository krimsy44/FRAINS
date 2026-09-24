<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusUpdate extends Model
{
    protected $fillable = ['order_id', 'user_id', 'status', 'message'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}