<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Stock;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $role = request()->user()->role?->name;
        if ($role === 'COMMERCIAL') {
            return to_route('admin.orders.index');
        }
        if ($role === 'DRIVER' || $role === 'DELIVERY_MANAGER') {
            return to_route('admin.deliveries.index');
        }
        if ($role === 'PRODUCER') {
            return to_route('admin.agriculture.index');
        }
        if ($role === 'STOCK_MANAGER') {
            return to_route('admin.stocks.index');
        }

        return view('admin.dashboard', [
            'productCount' => Product::count(),
            'categoryCount' => Category::count(),
            'lowStocks' => Stock::with('product')->whereRaw('quantity - reserved_quantity <= minimum_quantity')->get(),
            'pendingOrders' => Order::whereNotIn('status', ['COMPLETED', 'CANCELLED', 'REFUSED'])->count(),
            'customerCount' => Customer::count(),
            'producerCount' => Producer::count(),
            'orderCount' => Order::count(),
            'deliveryCount' => Delivery::whereNotIn('status', ['DELIVERED', 'CANCELLED'])->count(),
            'turnover' => Order::whereIn('status', ['DELIVERED', 'COMPLETED'])->sum('total'),
            'newQuotes' => Quote::where('status', 'REQUESTED')->count(),
            'lastOrders' => Order::with('customer.user')->latest()->limit(8)->get(),
        ]);
    }
}
