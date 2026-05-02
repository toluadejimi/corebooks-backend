<?php

namespace Database\Seeders;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::query()->create([
            'name' => 'Demo Owner',
            'email' => 'demo@salesapp.test',
            'password' => 'password',
        ]);

        $business = Business::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Demo Shop',
        ]);

        $user->businesses()->attach($business->id, ['role' => BusinessRole::Owner->value]);

        $location = Location::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Main',
            'is_default' => true,
        ]);

        $product = Product::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Sample Water 50cl',
            'sku' => 'SKU-001',
            'barcode' => '6281234567890',
            'cost_price' => 80,
            'selling_price' => 150,
            'low_stock_threshold' => 10,
            'track_batches' => false,
            'vat_rate' => 7.5,
            'version' => 1,
        ]);

        ProductBatch::query()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uuid' => (string) Str::uuid(),
            'qty' => 100,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'cost_price_snapshot' => 80,
            'version' => 1,
        ]);
    }
}
