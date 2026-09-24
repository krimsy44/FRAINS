<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'sku', 'description', 'unit', 'minimum_order_quantity', 'wholesale_available', 'base_price', 'image', 'is_active', 'is_featured', 'packaging', 'production_zone', 'quote_threshold'];

    protected function casts(): array
    {
        return ['wholesale_available' => 'boolean', 'is_active' => 'boolean', 'is_featured' => 'boolean', 'base_price' => 'decimal:2', 'minimum_order_quantity' => 'decimal:2'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class);
    }

    public function priceFor(float $quantity): float
    {
        $price = $this->prices()->where('is_active', true)->where('min_quantity', '<=', $quantity)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->where(fn ($q) => $q->whereNull('customer_type')->orWhere('customer_type', auth()->user()?->customer?->customer_type))
            ->where(fn ($query) => $query->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity))
            ->orderByRaw('customer_type IS NULL')->orderByDesc('min_quantity')->first();

        return (float) ($price?->price ?? $this->base_price);
    }
}
