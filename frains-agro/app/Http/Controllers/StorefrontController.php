<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\GalleryImage;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Publication;
use App\Models\Setting;

class StorefrontController extends Controller
{
    public function home()
    {
        return view('storefront.home', [
            'featuredProducts' => Product::query()->with(['category', 'stock'])->where('is_active', true)->orderByDesc('is_featured')->orderBy('name')->take(8)->get(),
            'categories' => Category::query()->where('is_active', true)->withCount('products')->get(),
            'publications' => Publication::published()->latest('published_at')->limit(3)->get(),
            'gallery' => GalleryImage::where('is_active', true)->latest()->limit(6)->get(),
            'settings' => Setting::values(),
            'producerCount' => Producer::where('status', 'active')->count(),
            'productCount' => Product::where('is_active', true)->count(),
            'zoneCount' => DeliveryZone::where('is_active', true)->count(),
            'wholesaleProducts' => Product::where('is_active', true)->where('wholesale_available', true)->limit(3)->get(),
        ]);
    }

    public function products(bool $wholesaleOnly = false)
    {
        request()->validate(['q' => 'nullable|string|max:120', 'zone' => 'nullable|string|max:120', 'category' => 'nullable|string|max:120', 'min_price' => 'nullable|numeric|min:0', 'max_price' => 'nullable|numeric|min:0', 'availability' => 'nullable|in:available,out', 'wholesale' => 'nullable|boolean']);
        $searchTerm = trim((string) request('q'));
        $wholesaleOnly = $wholesaleOnly || request()->boolean('wholesale');

        $products = Product::query()->with(['category', 'stock'])->where('is_active', true)
            ->when($searchTerm !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$searchTerm}%")
                ->orWhere('slug', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%")
            ))
            ->when($searchTerm === '' && request('category'), fn ($query, $category) => $query->whereHas('category', fn ($q) => $q->where('slug', $category)))
            ->when($searchTerm === '' && request('zone'), fn ($q, $v) => $q->where('production_zone', 'like', '%'.$v.'%'))
            ->when($searchTerm === '' && request()->filled('min_price'), fn ($q) => $q->where('base_price', '>=', request('min_price')))
            ->when($searchTerm === '' && request()->filled('max_price'), fn ($q) => $q->where('base_price', '<=', request('max_price')))
            ->when($wholesaleOnly, fn ($q) => $q->where('wholesale_available', true))
            ->when($searchTerm === '' && request('availability') === 'available', fn ($q) => $q->whereHas('stock', fn ($s) => $s->whereRaw('quantity > reserved_quantity')))
            ->when($searchTerm === '' && request('availability') === 'out', fn ($q) => $q->where(fn ($q) => $q->whereDoesntHave('stock')->orWhereHas('stock', fn ($s) => $s->whereRaw('quantity <= reserved_quantity'))))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('storefront.products', ['products' => $products, 'wholesaleOnly' => $wholesaleOnly, 'categories' => Category::where('is_active', true)->orderBy('name')->get()]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        return view('storefront.product', ['product' => $product->load(['category', 'prices', 'stock'])]);
    }
}
