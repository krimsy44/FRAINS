<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            ['name' => 'ADMIN', 'label' => 'Administrateur'], ['name' => 'MANAGER', 'label' => 'Gestionnaire'],
            ['name' => 'COMMERCIAL', 'label' => 'Gestionnaire commercial'], ['name' => 'STOCK_MANAGER', 'label' => 'Gestionnaire stock'],
            ['name' => 'DELIVERY_MANAGER', 'label' => 'Responsable livraison'], ['name' => 'DRIVER', 'label' => 'Livreur'],
            ['name' => 'PRODUCER', 'label' => 'Producteur'], ['name' => 'CUSTOMER', 'label' => 'Client'],
        ])->mapWithKeys(fn ($role) => [$role['name'] => Role::firstOrCreate(['name' => $role['name']], $role)]);

        User::firstOrCreate(['email' => 'admin@frains-agro.sn'], ['role_id' => $roles['ADMIN']->id, 'first_name' => 'Admin', 'last_name' => 'FRAINS', 'phone' => '770000000', 'password' => 'password', 'status' => 'active']);

        $vegetables = Category::firstOrCreate(['slug' => 'legumes'], ['name' => 'Légumes', 'description' => 'Légumes frais issus de nos productions', 'is_active' => true]);
        Category::firstOrCreate(['name' => 'Fruits'], ['slug' => 'fruits', 'is_active' => true]);
        foreach ([['name' => 'Dakar', 'fee' => 2000], ['name' => 'Pikine', 'fee' => 2500], ['name' => 'Guédiawaye', 'fee' => 2500], ['name' => 'Rufisque', 'fee' => 3500]] as $zone) {
            DeliveryZone::firstOrCreate(['name' => $zone['name']], ['base_fee' => $zone['fee'], 'is_active' => true]);
        }
        foreach ([
            ['name' => 'Tomate fraîche', 'sku' => 'TOM-001', 'price' => 700, 'stock' => 850, 'featured' => true],
            ['name' => 'Oignon jaune', 'sku' => 'OIG-001', 'price' => 500, 'stock' => 1200, 'featured' => true],
            ['name' => 'Carotte', 'sku' => 'CAR-001', 'price' => 850, 'stock' => 420, 'featured' => true],
            ['name' => 'Concombre', 'sku' => 'CON-001', 'price' => 650, 'stock' => 0, 'featured' => false],
        ] as $item) {
            $product = Product::firstOrCreate(['sku' => $item['sku']], ['category_id' => $vegetables->id, 'name' => $item['name'], 'slug' => Str::slug($item['name']).'-'.Str::lower($item['sku']), 'description' => 'Produit frais, sélectionné par FRAINS Agro.', 'unit' => 'KG', 'minimum_order_quantity' => 1, 'wholesale_available' => true, 'base_price' => $item['price'], 'is_active' => true, 'is_featured' => $item['featured']]);
            Stock::firstOrCreate(['product_id' => $product->id], ['quantity' => $item['stock'], 'minimum_quantity' => 50]);
        }
    }
}
