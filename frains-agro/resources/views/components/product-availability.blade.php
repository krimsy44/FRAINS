@props(['product'])
@php($available = ($product->stock?->available_quantity ?? 0) > 0)
<span class="product-stock-badge {{ $available ? 'available' : 'unavailable' }}" style="align-self:flex-start">
    {{ $available ? 'Disponible' : 'Indisponible' }}
</span>
