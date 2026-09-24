<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
