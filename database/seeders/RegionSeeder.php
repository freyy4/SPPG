<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['name' => 'Pusat', 'code' => 'PST', 'description' => 'Kantor Pusat SPPG'],
            ['name' => 'Surabaya', 'code' => 'SBY', 'description' => 'Dapur Wilayah Harpa 2'],
            ['name' => 'Cilacap', 'code' => 'CLP', 'description' => 'Dapur Wilayah Harpa 3'],
        ];

        foreach ($regions as $region) {
            Region::updateOrCreate(['name' => $region['name']], $region);
        }
    }
}