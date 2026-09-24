<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        return view('admin.products.index', ['products' => Product::with(['category', 'stock'])->latest()->paginate(15)]);
    }

    public function create()
    {
        return view('admin.products.form', ['product' => new Product, 'categories' => $this->categories()]);
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));
        Stock::create(['product_id' => $product->id, 'minimum_quantity' => $request->input('minimum_stock', 0)]);

        return to_route('admin.products.index')->with('success', 'Produit créé.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', ['product' => $product->load('stock'), 'categories' => $this->categories()]);
    }

    public function update(Request $request, Product $product)
    {
        if ($request->input('unit') !== $product->unit && (($product->stock?->quantity ?? 0) > 0 || StockMovement::where('product_id', $product->id)->exists())) {
            throw ValidationException::withMessages(['unit' => 'Un produit possédant un stock ou un historique conserve son unité. Créez une référence distincte pour une autre unité.']);
        }
        $product->update($this->validated($request, $product));
        $product->stock()->updateOrCreate([], ['minimum_quantity' => $request->input('minimum_stock') ?? $product->stock?->minimum_quantity ?? 0]);

        return to_route('admin.products.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        return to_route('admin.products.index')->with('success', 'Produit désactivé ; son historique est conservé.');
    }

    private function categories()
    {
        return Category::where('is_active', true)->orderBy('name')->get();
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'category_id' => ['nullable', 'exists:categories,id'], 'name' => ['required', 'string', 'max:120'],
            'sku' => ['required', 'string', 'max:60', 'unique:products,sku,'.($product?->id ?? 'NULL')],
            'description' => ['nullable', 'string'], 'unit' => ['required', 'in:KG,TONNE,SAC,CAISSE,PANIER,UNITE,LITRE'],
            'minimum_order_quantity' => ['required', 'numeric', 'min:0.01'], 'base_price' => ['required', 'numeric', 'min:0'],
            'wholesale_available' => ['boolean'], 'is_active' => ['boolean'], 'is_featured' => ['boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower($data['sku']);
        unset($data['minimum_stock']);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('media', 'public');
        } else {
            unset($data['image']);
        }
        $data['wholesale_available'] = $request->boolean('wholesale_available');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }
}
