<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Harvest;
use App\Models\Order;
use App\Models\Producer;
use App\Models\Quote;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'export' => 'nullable|in:csv']);
        $from = $data['from'] ?? today()->startOfYear()->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        $role = auth()->user()->role?->name;
        $sales = in_array($role, ['ADMIN', 'MANAGER', 'COMMERCIAL']);
        $logistics = in_array($role, ['ADMIN', 'MANAGER', 'DELIVERY_MANAGER']);
        $inventory = in_array($role, ['ADMIN', 'MANAGER', 'STOCK_MANAGER']);
        $agriculture = in_array($role, ['ADMIN', 'MANAGER']);
        $orders = $sales ? Order::with(['items', 'receipts', 'payment'])->whereBetween('ordered_at', [$from.' 00:00:00', $to.' 23:59:59'])->get() : collect();
        $completed = $orders->whereIn('status', ['DELIVERED', 'COMPLETED']);
        $metrics = [];
        if ($sales) {
            $metrics = ['Commandes' => $orders->count(), 'Chiffre d’affaires livré (FCFA)' => round($completed->sum('total'), 2), 'Panier moyen livré (FCFA)' => round($completed->avg('total') ?? 0, 2), 'Clients' => Customer::count(), 'Clients actifs' => Customer::where('status', 'active')->count(), 'Taux d’annulation (%)' => $orders->isEmpty() ? 0 : round(100 * $orders->whereIn('status', ['CANCELLED', 'REFUSED'])->count() / $orders->count(), 2)];
        }
        $stocks = $inventory ? Stock::with('product')->get() : collect();
        $movements = $inventory ? StockMovement::with('product')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->get() : collect();
        $deliveries = $logistics ? Delivery::with('order')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->get() : collect();
        $harvests = $agriculture ? Harvest::with('cultivation.product')->whereBetween('harvested_at', [$from, $to])->get() : collect();
        if ($agriculture) {
            $metrics['Producteurs'] = Producer::count();
        }
        if ($logistics) {
            $metrics['Livraisons'] = $deliveries->count();
            $metrics['Taux de livraison (%)'] = $deliveries->isEmpty() ? 0 : round(100 * $deliveries->where('status', 'DELIVERED')->count() / $deliveries->count(), 2);
            $metrics['Délai moyen livraison (jours)'] = round($deliveries->whereNotNull('delivered_at')->avg(fn ($d) => $d->created_at->diffInDays($d->delivered_at)) ?? 0, 2);
        }
        $monthly = $completed->groupBy(fn ($o) => $o->ordered_at->format('Y-m'))->map(fn ($g) => $g->sum('total'));
        $top = $completed->flatMap->items->groupBy('product_id')->map(fn ($items) => ['name' => $items->first()->product_name, 'unit' => $items->first()->unit, 'quantity' => $items->sum('quantity'), 'total' => $items->sum('total')])->sortByDesc('total')->take(10);
        $latePayments = $sales ? Order::whereDate('payment_due_date', '<', today())->whereNotIn('status', ['CANCELLED', 'REFUSED'])->where('payment_status', '!=', 'PAID')->get() : collect();
        $lateDeliveries = $logistics ? Delivery::whereDate('scheduled_date', '<', today())->whereNotIn('status', ['DELIVERED', 'CANCELLED'])->get() : collect();
        $newQuotes = $sales ? Quote::where('status', 'REQUESTED')->count() : 0;
        $charts = [];
        if ($sales) {
            foreach ($completed->flatMap->items->groupBy('unit') as $unit => $items) {
                $metrics['Volume vendu ('.$unit.')'] = $items->sum('quantity');
            }
            $charts[] = ['title' => 'Commandes par mois', 'unit' => 'commandes', 'points' => $orders->groupBy(fn ($o) => $o->ordered_at->format('Y-m'))->map->count()];
            $charts[] = ['title' => 'Ventes livrées par mois', 'unit' => 'ventes', 'points' => $completed->groupBy(fn ($o) => $o->ordered_at->format('Y-m'))->map->count()];
            $charts[] = ['title' => 'Nouveaux clients par mois', 'unit' => 'clients', 'points' => Customer::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->get()->groupBy(fn ($c) => $c->created_at->format('Y-m'))->map->count()];
            $charts[] = ['title' => 'Meilleures ventes en valeur', 'unit' => 'FCFA', 'points' => $top->mapWithKeys(fn ($item) => [$item['name'] => $item['total']])];
        }
        if ($logistics) {
            $charts[] = ['title' => 'Livraisons créées par mois', 'unit' => 'livraisons', 'points' => $deliveries->groupBy(fn ($d) => $d->created_at->format('Y-m'))->map->count()];
        }
        if ($inventory) {
            foreach ($stocks->groupBy('product.unit') as $unit => $group) {
                $metrics['Stock disponible ('.$unit.')'] = $group->sum('available_quantity');
                $charts[] = ['title' => 'Stock disponible par produit', 'unit' => $unit, 'points' => $group->mapWithKeys(fn ($s) => [$s->product->name => $s->available_quantity])];
            }
        }
        if ($agriculture) {
            foreach ($harvests->groupBy('cultivation.product.unit') as $unit => $group) {
                $metrics['Volume récolté ('.$unit.')'] = $group->sum('quantity');
                $metrics['Pertes agricoles ('.$unit.')'] = $group->sum('loss_quantity');
                $charts[] = ['title' => 'Récoltes par mois', 'unit' => $unit, 'points' => $group->groupBy(fn ($h) => substr($h->harvested_at, 0, 7))->map(fn ($g) => $g->sum('quantity'))];
            }
        }
        if (($data['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($metrics, $stocks, $movements, $deliveries, $harvests, $top) {
                $f = fopen('php://output', 'w');
                fwrite($f, "\xEF\xBB\xBF");
                $row = function (array $cells) use ($f) {
                    fputcsv($f, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, $cells), ';');
                };
                $row(['Domaine', 'Indicateur / référence', 'Valeur', 'Unité']);
                foreach ($metrics as $label => $value) {
                    $row(['Indicateur', $label, $value, '']);
                }
                foreach ($stocks as $s) {
                    $row(['Stock disponible', $s->product?->name, $s->available_quantity, $s->product?->unit]);
                    $row(['Stock réservé', $s->product?->name, $s->reserved_quantity, $s->product?->unit]);
                }
                foreach ($movements as $m) {
                    $row(['Mouvement '.$m->type, $m->product?->name, $m->quantity, $m->product?->unit]);
                }
                foreach ($top as $item) {
                    $row(['Vendu', $item['name'], $item['quantity'], $item['unit']]);
                }
                foreach ($harvests as $h) {
                    $row(['Récolte', $h->cultivation->product->name, $h->quantity, $h->cultivation->product->unit]);
                    $row(['Perte récolte', $h->cultivation->product->name, $h->loss_quantity, $h->cultivation->product->unit]);
                }
                foreach ($deliveries as $d) {
                    $row(['Livraison', $d->delivery_number, $d->status, $d->driver_name]);
                }
                fclose($f);
            }, 'frains-rapport-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $audit = $role === 'ADMIN' ? AuditLog::latest()->limit(100)->get() : collect();
        $messages = $sales ? DB::table('contact_messages')->latest()->limit(100)->get() : collect();

        return view('admin.reports',compact('from','to','metrics','stocks','movements','deliveries','harvests','monthly','top','latePayments','lateDeliveries','newQuotes','audit','messages','charts'));
    }
}
