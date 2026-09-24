<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
        });
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->unsignedBigInteger('value')->default(0);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject')->nullable();
            $t->json('details')->nullable();
            $t->timestamps();
        });
        Schema::table('products', function (Blueprint $t) {
            $t->string('packaging')->nullable();
            $t->string('production_zone')->nullable();
            $t->decimal('quote_threshold', 12, 2)->nullable();
        });
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('phone', 30);
            $t->string('vehicle')->nullable();
            $t->string('vehicle_type')->nullable();
            $t->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_available')->default(true);
            $t->timestamps();
        });
        Schema::table('deliveries', function (Blueprint $t) {
            $t->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->date('payment_due_date')->nullable();
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->decimal('delivered_quantity', 12, 2)->default(0);
            $t->string('packaging')->nullable();
        });
        Schema::create('payment_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reference')->unique();
            $t->string('external_reference')->nullable();
            $t->decimal('amount', 14, 2);
            $t->string('method');
            $t->timestamp('received_at');
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $t->string('number')->unique();
            $t->json('snapshot');
            $t->timestamp('issued_at');
            $t->timestamps();
        });
        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('delivery_zone_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('status')->default('REQUESTED');
            $t->text('delivery_address');
            $t->date('requested_date');
            $t->date('valid_until')->nullable();
            $t->decimal('delivery_fee', 14, 2)->nullable();
            $t->text('notes')->nullable();
            $t->text('proposal_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('quote_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('product_name');
            $t->string('unit');
            $t->string('packaging')->nullable();
            $t->decimal('quantity', 12, 2);
            $t->decimal('unit_price', 14, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->text('address');
            $t->string('city');
            $t->foreignId('delivery_zone_id')->constrained()->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('publications', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('category');
            $t->text('content');
            $t->string('image')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('DRAFT');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('gallery_images', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('category');
            $t->string('image');
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('phone', 30)->nullable();
            $t->string('subject');
            $t->text('message');
            $t->string('status')->default('NEW');
            $t->timestamps();
        });
        Schema::create('producers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('phone', 30);
            $t->text('address')->nullable();
            $t->string('zone');
            $t->date('joined_at');
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('parcels', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('producer_id')->constrained()->restrictOnDelete();
            $t->string('location');
            $t->decimal('area', 12, 2);
            $t->string('crop_type')->nullable();
            $t->string('status')->default('ACTIVE');
            $t->date('started_at');
            $t->timestamps();
        });
        Schema::create('cultivations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->decimal('area', 12, 2);
            $t->date('planted_at');
            $t->date('expected_harvest_at');
            $t->decimal('expected_quantity', 12, 2);
            $t->string('status')->default('GROWING');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('harvests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cultivation_id')->constrained()->restrictOnDelete();
            $t->date('harvested_at');
            $t->decimal('quantity', 12, 2);
            $t->decimal('loss_quantity', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'harvests', 'cultivations', 'parcels', 'producers', 'contact_messages', 'gallery_images', 'publications', 'addresses', 'quote_items', 'quotes', 'invoices', 'payment_receipts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['delivered_quantity', 'packaging']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('payment_due_date'));
        Schema::table('deliveries', fn (Blueprint $t) => $t->dropConstrainedForeignId('driver_id'));
        Schema::dropIfExists('drivers');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['packaging', 'production_zone', 'quote_threshold']));
        foreach (['audit_logs', 'document_sequences', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
