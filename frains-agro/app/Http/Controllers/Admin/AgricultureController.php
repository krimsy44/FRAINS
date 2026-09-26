<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cultivation;
use App\Models\Harvest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgricultureController extends Controller
{
    private function cultures()
    {
        return Cultivation::with(['parcel.producer', 'product'])->when(auth()->user()->role?->name === 'PRODUCER', fn ($q) => $q->whereHas('parcel.producer', fn ($q) => $q->where('user_id', auth()->id())));
    }

    public function index()
    {
        $cultures = $this->cultures()->withSum('harvests', 'quantity')->withSum('harvests', 'loss_quantity')->get();
        $harvests = Harvest::with('cultivation.product')->whereIn('cultivation_id', $cultures->pluck('id'))->latest()->paginate(20);
        foreach ($harvests as $harvest) {
            $lot = DB::table('stock_lots')->where('harvest_id', $harvest->id)->first();
            $harvest->remaining = $lot?->remaining_quantity;
            $harvest->sold = $lot ? DB::table('stock_lot_allocations')->join('stock_movements', 'stock_movements.id', '=', 'stock_lot_allocations.stock_movement_id')->where('stock_lot_id', $lot->id)->where('stock_movements.type', 'SALE')->sum('stock_lot_allocations.quantity') : null;
        }

        return view('admin.agriculture', compact('cultures', 'harvests'));
    }

    public function edit(Harvest $harvest)
    {
        $culture = $this->cultures()->findOrFail($harvest->cultivation_id);

        return view('admin.harvest-edit', compact('harvest', 'culture'));
    }

    public function update(Request $request, Harvest $harvest)
    {
        $this->cultures()->findOrFail($harvest->cultivation_id);
        $data = $request->validate([
            'harvested_at' => 'required|date|before_or_equal:today',
            'quantity' => 'required|numeric|min:0.01|max:999999999|decimal:0,2',
            'loss_quantity' => 'required|numeric|min:0|lte:quantity|decimal:0,2',
            'notes' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($harvest, $data) {
            $culture = $this->cultures()->whereKey($harvest->cultivation_id)->lockForUpdate()->firstOrFail();
            $harvest = Harvest::whereKey($harvest->id)->lockForUpdate()->firstOrFail();
            if (strtotime($data['harvested_at']) < strtotime($culture->planted_at)) {
                throw ValidationException::withMessages(['harvested_at' => 'La date de récolte doit être égale ou postérieure au '.\Illuminate\Support\Carbon::parse($culture->planted_at)->format('d/m/Y').' (date de plantation). Si la plantation est incorrecte, corrigez-la dans Gérer les cultures.']);
            }
            $before = $harvest->only(['harvested_at', 'quantity', 'loss_quantity', 'notes']);
            $usable = round($data['quantity'] - $data['loss_quantity'], 2);
            $difference = round($usable - ($harvest->quantity - $harvest->loss_quantity), 2);
            if ($difference != 0) {
                $product = Product::whereKey($culture->product_id)->lockForUpdate()->firstOrFail();
                $stock = $product->stock()->lockForUpdate()->firstOrFail();
                $lot = DB::table('stock_lots')->where('harvest_id', $harvest->id)->lockForUpdate()->first();
                if (! $lot) {
                    throw ValidationException::withMessages(['quantity' => 'Cette ancienne récolte ne possède pas de lot traçable. Sa quantité nette ne peut pas être modifiée.']);
                }
                if (round($lot->remaining_quantity + $difference, 2) < 0 || round($stock->quantity + $difference - $stock->reserved_quantity, 2) < 0) {
                    throw ValidationException::withMessages(['quantity' => 'Cette diminution dépasse le stock disponible : une partie de la récolte est déjà sortie ou réservée.']);
                }
                DB::table('stock_lots')->where('id', $lot->id)->update([
                    'quantity' => $usable,
                    'remaining_quantity' => round($lot->remaining_quantity + $difference, 2),
                    'updated_at' => now(),
                ]);
                $stock->update(['quantity' => round($stock->quantity + $difference, 2)]);
                StockMovement::where('product_id', $product->id)->where('type', 'HARVEST')
                    ->where('reason', 'Récolte #'.$harvest->id)->update(['quantity' => $usable]);
            }
            $harvest->update($data);
            AuditLog::record('harvest.updated', 'harvest:'.$harvest->id, ['before' => $before, 'after' => $data]);
        });

        return to_route('admin.agriculture.index')->with('success', 'Récolte modifiée et stock mis à jour.');
    }

    public function harvest(Request $request)
    {
        $data = $request->validate(['cultivation_id' => 'required|exists:cultivations,id', 'harvested_at' => 'required|date|before_or_equal:today', 'quantity' => 'required|numeric|min:0.01|max:999999999|decimal:0,2', 'loss_quantity' => 'required|numeric|min:0|lte:quantity|decimal:0,2', 'notes' => 'nullable|string|max:2000']);
        $culture = $this->cultures()->findOrFail($data['cultivation_id']);
        $data += $request->validate(['submission_token' => 'nullable|uuid']);
        DB::transaction(function () use ($culture, $data) {
            $culture = Cultivation::whereKey($culture->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['submission_token']) && Harvest::where('submission_token', $data['submission_token'])->where('cultivation_id', $culture->id)->exists()) {
                return;
            }
            if ($culture->status === 'CLOSED') {
                throw ValidationException::withMessages(['cultivation_id' => 'Cette culture est terminée. Choisissez une culture en cours.']);
            }
            if (strtotime($data['harvested_at']) < strtotime($culture->planted_at)) {
                throw ValidationException::withMessages(['harvested_at' => 'La date de récolte doit être égale ou postérieure au '.\Illuminate\Support\Carbon::parse($culture->planted_at)->format('d/m/Y').' (date de plantation). Si la plantation est incorrecte, corrigez-la dans Gérer les cultures.']);
            }
            $harvest = Harvest::create($data + ['user_id' => auth()->id()]);
            $product = Product::whereKey($culture->product_id)->lockForUpdate()->firstOrFail();
            $stock = $product->stock()->firstOrCreate([], ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]);
            $usable = round($data['quantity'] - $data['loss_quantity'], 2);
            app(StockLedger::class)->enter($stock, $usable, $harvest->id);
            $stock->increment('quantity', $usable);
            StockMovement::create(['product_id' => $product->id, 'user_id' => auth()->id(), 'type' => 'HARVEST', 'quantity' => $usable, 'reason' => 'Récolte #'.$harvest->id]);
            $culture->update(['status' => 'HARVESTING']);
            AuditLog::record('harvest.created', 'harvest:'.$harvest->id, ['usable' => $usable, 'loss' => $data['loss_quantity']]);
        });

        return back()->with('success','Récolte enregistrée et stock alimenté hors pertes.');
    }
}
