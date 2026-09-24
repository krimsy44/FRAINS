<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('harvest_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->decimal('quantity', 14, 2);
            $t->decimal('remaining_quantity', 14, 2);
            $t->timestamps();
        });
        Schema::create('stock_lot_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_lot_id')->constrained()->restrictOnDelete();
            $t->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 14, 2);
            $t->timestamps();
        });
        // Existing stock has unknown origin: preserve it as a clearly unassigned lot.
        DB::table('stocks')->where('quantity', '>', 0)->orderBy('id')->chunkById(200, function ($stocks) {
            foreach ($stocks as $stock) {
                DB::table('stock_lots')->insert(['product_id' => $stock->product_id, 'quantity' => $stock->quantity, 'remaining_quantity' => $stock->quantity, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Schema::table('harvests', fn (Blueprint $t) => $t->uuid('submission_token')->nullable()->unique());
        Schema::table('payment_receipts', fn (Blueprint $t) => $t->uuid('submission_token')->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('payment_receipts', fn (Blueprint $t) => $t->dropColumn('submission_token'));
        Schema::table('harvests', fn (Blueprint $t) => $t->dropColumn('submission_token'));
        Schema::dropIfExists('stock_lot_allocations');
        Schema::dropIfExists('stock_lots');
    }
};
