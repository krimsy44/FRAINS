<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = ['order_id', 'delivery_zone_id', 'delivery_number', 'status', 'scheduled_date', 'assigned_at', 'delivered_at', 'delivery_address', 'customer_phone', 'notes', 'driver_name', 'driver_phone', 'driver_id'];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'assigned_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function zone()
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }
}
