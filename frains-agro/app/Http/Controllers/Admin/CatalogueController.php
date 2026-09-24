<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogueController extends Controller
{
    public function edit(Product $product)
    {
        return view('admin.catalogue', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate(['packaging' => 'nullable|string|max:120', 'production_zone' => 'nullable|string|max:120', 'quote_threshold' => 'nullable|numeric|min:0.01|max:999999999|decimal:0,2', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096']);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('media', 'public');
        } else {
            unset($data['image']);
        }
        $product->update($data);
        AuditLog::record('product.presentation', 'product:'.$product->id);

        return back()->with('success', 'Présentation enregistrée.');
    }

    public function price(Request $request, Product $product)
    {
        $data = $request->validate(['min_quantity' => 'required|numeric|min:0.01|max:999999999|decimal:0,2', 'max_quantity' => 'nullable|numeric|gte:min_quantity|max:999999999|decimal:0,2', 'price' => 'required|numeric|min:0|max:99999999|decimal:0,2', 'customer_type' => 'nullable|in:PARTICULIER,PROFESSIONNEL,REVENDEUR,GROSSISTE,RESTAURANT,HOTEL,SUPERMARCHE,ENTREPRISE,DISTRIBUTEUR', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at']);
        DB::transaction(function () use ($product, $data) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $query = $product->prices()->where('is_active', true)->where('customer_type', $data['customer_type'] ?? null)->where('min_quantity', '<=', $data['max_quantity'] ?? 999999999)->where(fn ($q) => $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $data['min_quantity']));
            if ($query->exists()) {
                throw ValidationException::withMessages(['min_quantity' => 'Ce palier chevauche un tarif actif du même type de client. Désactivez l’ancien tarif avant de le remplacer.']);
            }
            $product->prices()->create($data + ['is_active' => true]);
            AuditLog::record('product.price', 'product:'.$product->id, $data);
        });

        return back()->with('success', 'Tarif ajouté.');
    }

    public function disable(Product $product, ProductPrice $price)
    {
        abort_unless($price->product_id === $product->id, 404);
        $price->update(['is_active' => false]);
        AuditLog::record('price.disabled', 'price:'.$price->id);

        return back()->with('success','Tarif désactivé.');
    }
}
