<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['user_id', 'delivery_zone_id', 'customer_type', 'company_name', 'business_registration', 'credit_limit', 'status', 'first_name', 'last_name', 'phone'];

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name) ?: ($this->user?->name ?? 'Client');
    }

    public function getContactPhoneAttribute(): ?string
    {
        return $this->phone ?? $this->user?->phone;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['account_deleted_at' => 'datetime'];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryZone()
    {
        return $this->belongsTo(DeliveryZone::class);
    }
}
