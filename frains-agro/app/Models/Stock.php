<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = ['product_id', 'quantity', 'reserved_quantity', 'minimum_quantity'];

    protected $appends = ['available_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'reserved_quantity' => 'decimal:2', 'minimum_quantity' => 'decimal:2'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getAvailableQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->reserved_quantity);
    }
}
