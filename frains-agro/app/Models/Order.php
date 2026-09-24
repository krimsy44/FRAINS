<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUSES = ['NEW' => 'Nouvelle', 'CONFIRMED' => 'Confirmée', 'PREPARING' => 'En préparation', 'READY' => 'Prête', 'SHIPPING' => 'En livraison', 'PARTIALLY_DELIVERED' => 'Partiellement livrée', 'DELIVERED' => 'Livrée', 'COMPLETED' => 'Terminée', 'CANCELLED' => 'Annulée', 'REFUSED' => 'Refusée'];

    public function nextStatuses(): array
    {
        return match ($this->status) {
            'NEW' => ['CONFIRMED', 'REFUSED', 'CANCELLED'],
            'CONFIRMED' => ['PREPARING', 'CANCELLED'],
            'PREPARING' => ['READY', 'CANCELLED'],
            'READY' => ['SHIPPING', 'CANCELLED'],
            'SHIPPING', 'PARTIALLY_DELIVERED' => ['DELIVERED'],
            'DELIVERED' => ['COMPLETED'],
            default => [],
        };
    }

    public function canBeCancelledByCustomer(): bool
    {
        return in_array('CANCELLED', $this->nextStatuses(), true)
            && ! in_array($this->payment_status, ['PAID', 'PARTIAL'], true)
            && $this->paid_amount <= 0;
    }

    protected $fillable = ['order_number', 'customer_id', 'delivery_zone_id', 'status', 'payment_status', 'delivery_status', 'subtotal', 'delivery_fee', 'discount', 'total', 'delivery_address', 'notes', 'ordered_at', 'payment_due_date', 'checkout_key'];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime', 'payment_due_date' => 'date'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function receipts()
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    public function statusUpdates()
    {
        return $this->hasMany(OrderStatusUpdate::class);
    }
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function getPaidAmountAttribute(): float
    {
        $receipts = (float) $this->receipts()->sum('amount');

        return $receipts > 0 ? $receipts : ($this->payment?->status === 'PAID' ? (float) $this->payment->amount : 0);
    }

    public function getBalanceAttribute(): float
    {
        return max(0, round((float) $this->total - $this->paid_amount, 2));
    }
}
