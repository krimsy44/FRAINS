<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedger
{
    // Call inside the same transaction, with the stock row locked, before changing physical quantity.
    private function synchronize(Stock $stock): void
    {
        $tracked = (float) DB::table('stock_lots')->where('product_id', $stock->product_id)->sum('remaining_quantity');
        $difference = round($stock->quantity - $tracked, 2);
        if ($difference > 0) {
            DB::table('stock_lots')->insert(['product_id' => $stock->product_id, 'quantity' => $difference, 'remaining_quantity' => $difference, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($difference < 0) {
            throw ValidationException::withMessages(['quantity' => 'Écart entre stock physique et lots : une régularisation est nécessaire.']);
        }
    }

    public function enter(Stock $stock, float $quantity, ?int $harvestId = null): void
    {
        $this->synchronize($stock);
        DB::table('stock_lots')->insert(['product_id' => $stock->product_id, 'harvest_id' => $harvestId, 'quantity' => $quantity, 'remaining_quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function consume(Stock $stock, StockMovement $movement): void
    {
        $this->synchronize($stock);
        $remaining = (float) $movement->quantity;
        $lots = DB::table('stock_lots')->where('product_id', $stock->product_id)->where('remaining_quantity', '>', 0)->orderBy('id')->lockForUpdate()->get();
        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $used = min($remaining, (float) $lot->remaining_quantity);
            DB::table('stock_lots')->where('id', $lot->id)->update(['remaining_quantity' => round($lot->remaining_quantity - $used, 2), 'updated_at' => now()]);
            DB::table('stock_lot_allocations')->insert(['stock_lot_id' => $lot->id, 'stock_movement_id' => $movement->id, 'quantity' => $used, 'created_at' => now(), 'updated_at' => now()]);
            $remaining = round($remaining - $used, 2);
        }
        if ($remaining > 0) {
            throw ValidationException::withMessages(['quantity' => 'Les lots disponibles ne couvrent pas cette sortie.']);
        }
    }
}
