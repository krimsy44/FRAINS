<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requested_date' => 'date', 'valid_until' => 'date'];
    }

    public const STATUSES = ['REQUESTED' => 'Demande reçue', 'REVIEWING' => 'En analyse', 'OFFERED' => 'Proposition envoyée', 'ACCEPTED' => 'Accepté et converti', 'REJECTED' => 'Refusé', 'DECLINED' => 'Décliné par le client'];

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function zone()
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function getTotalAttribute(): float
    {
        return round($this->items->sum(fn ($i) => round($i->quantity * $i->unit_price, 2)) + $this->delivery_fee, 2);
    }
}
