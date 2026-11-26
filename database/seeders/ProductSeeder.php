<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// Loads the demo catalogue (the old products.json) into the database.
// Run: php artisan db:seed --class=ProductSeeder
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = json_decode(
            file_get_contents(database_path('seeders/data/products.json')),
            true
        )['products'] ?? [];

        $shops = [];
        $count = 0;

        foreach ($items as $item) {
            $merchant = $item['merchant'];

            if (!isset($shops[$merchant])) {
                $count++;
                $email = 'vendor-' . Str::slug($merchant) . '@autoclaw.test';

                $vendor = User::where('email', $email)->first();

                if (!$vendor) {
                    $vendor = new User();
                    $vendor->firstname = 'Demo';
                    $vendor->surname = $merchant;
                    $vendor->email = $email;
                    $vendor->phone = sprintf('0801000%04d', $count);
                    $vendor->password = Str::random(32);
                    $vendor->role = 'vendor';
                    $vendor->email_verified_at = now();
                    $vendor->save();
                }

                $shops[$merchant] = Shop::firstOrCreate(
                    ['vendor_id' => $vendor->id, 'name' => $merchant],
                    [
                        'address' => 'Demo address',
                        'country' => 'Nigeria',
                        'state' => 'Lagos',
                        'city' => 'Lagos',
                        'is_verified' => (bool) ($item['verified'] ?? false),
                    ]
                );
            }

            // The old JSON had a hand-typed selling price; turn the gap into a fixed discount
            $gap = $item['initial_price'] - $item['selling_price'];

            Product::firstOrCreate(
                ['shop_id' => $shops[$merchant]->id, 'name' => $item['name']],
                [
                    'brand' => $item['brand'],
                    'category' => $item['category'],
                    'description' => $item['desc'],
                    'initial_price' => $item['initial_price'],
                    'discount_type' => $gap > 0 ? 'fixed' : null,
                    'discount_value' => $gap > 0 ? $gap : null,
                    'stock' => $item['stock'] ?? 25,
                ]
            );
        }
    }
}