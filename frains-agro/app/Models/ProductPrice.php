<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = ['product_id', 'min_quantity', 'max_quantity', 'price', 'customer_type', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'is_active' => 'boolean', 'price' => 'decimal:2'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
