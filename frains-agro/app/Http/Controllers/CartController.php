<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cart = collect(session('cart', []))->map(function ($line) {
            $product = Product::find($line['id']);
            if ($product) {
                $line['unit_price'] = $product->priceFor((float) $line['quantity']);
                $line['total'] = round($line['unit_price'] * $line['quantity'], 2);
            }
            return $line;
        });
        if (! session()->has('checkout_token')) {
            session(['checkout_token' => (string) \Illuminate\Support\Str::uuid()]);
        }

        return view('cart.index', ['cart' => $cart, 'total' => $cart->sum('total'), 'zones' => \App\Models\DeliveryZone::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'decimal:0,2', 'max:9999999999.99', 'min:'.$product->minimum_order_quantity]]);
        $quantity = (float) $data['quantity'];
        $stock = $product->stock?->available_quantity ?? 0;

        if ($stock < $quantity) {
            return back()->with('error', 'La quantité demandée dépasse le stock disponible.');
        }

        $cart = session('cart', []);
        $key = (string) $product->id;
        $newQuantity = round(($cart[$key]['quantity'] ?? 0) + $quantity, 2);
        if ($newQuantity > $stock) {
            return back()->with('error', 'La quantité totale du panier dépasse le stock disponible.');
        }
        if ($product->quote_threshold && $newQuantity >= $product->quote_threshold) {
            return redirect()->route('quotes.start', ['product' => $product->id])->with('error', 'Cette quantité nécessite un devis personnalisé.');
        }

        $unitPrice = $product->priceFor($newQuantity);
        $cart[$key] = ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'unit' => $product->unit, 'quantity' => $newQuantity, 'unit_price' => $unitPrice, 'total' => $newQuantity * $unitPrice];
        session(['cart' => $cart]);

        return redirect()->route('cart.index')->with('success', "{$product->name} a été ajouté au panier.");
    }

    public function update(Request $request, string $productId)
    {
        $data = $request->validate(['quantity' => ['required', 'numeric', 'decimal:0,2', 'max:9999999999.99', 'min:0']]);
        $cart = session('cart', []);
        abort_unless(isset($cart[$productId]), 404);
        if ((float) $data['quantity'] === 0.0) {
            unset($cart[$productId]);
            session(['cart' => $cart]);

            return back();
        }
        $product = Product::findOrFail($productId);
        abort_unless($product->is_active, 404);
        $request->validate(['quantity' => ['numeric', 'min:'.$product->minimum_order_quantity]]);
        if ($data['quantity'] > ($product->stock?->available_quantity ?? 0)) {
            return back()->with('error', 'Stock insuffisant.');
        }
        $cart[$productId]['quantity'] = (float) $data['quantity'];
        $cart[$productId]['unit_price'] = $product->priceFor((float) $data['quantity']);
        $cart[$productId]['total'] = $cart[$productId]['quantity'] * $cart[$productId]['unit_price'];
        session(['cart' => $cart]);

        return back()->with('success', 'Panier mis à jour.');
    }
}
