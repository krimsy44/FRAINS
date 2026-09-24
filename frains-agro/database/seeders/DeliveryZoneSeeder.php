<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['name' => 'Dakar', 'fee' => 2000], ['name' => 'Pikine', 'fee' => 2500], ['name' => 'Guédiawaye', 'fee' => 2500], ['name' => 'Rufisque', 'fee' => 3500]] as $zone) {
            DeliveryZone::firstOrCreate(['name' => $zone['name']], ['base_fee' => $zone['fee'], 'is_active' => true]);
        }
    }
}
