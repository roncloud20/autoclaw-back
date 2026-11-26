<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discounts become real data instead of a hand-typed "selling_price":
 *   initial_price        = the normal price
 *   discount_*           = optional percent/fixed discount with an optional schedule
 * The price a customer pays is calculated from those (see Product::effective_price).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('discount_type', 10)->nullable()->after('initial_price'); // percent | fixed
            $table->unsignedInteger('discount_value')->nullable()->after('discount_type');
            $table->timestamp('discount_starts_at')->nullable()->after('discount_value');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
            $table->index(['discount_type', 'discount_starts_at', 'discount_ends_at'], 'products_discount_window_index');
        });

        // Keep existing prices: any gap between initial and selling price becomes a permanent fixed discount
        DB::table('products')
            ->whereColumn('selling_price', '<', 'initial_price')
            ->update([
                'discount_type' => 'fixed',
                'discount_value' => DB::raw('initial_price - selling_price'),
            ]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('selling_price')->default(0)->after('initial_price');
        });

        // Best effort: store the price as it is right now
        DB::table('products')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $price = $row->initial_price;

                if ($row->discount_type === 'fixed') {
                    $price = max($row->initial_price - $row->discount_value, 0);
                } elseif ($row->discount_type === 'percent') {
                    $price = (int) round($row->initial_price * (100 - $row->discount_value) / 100);
                }

                DB::table('products')->where('id', $row->id)->update(['selling_price' => $price]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_discount_window_index');
            $table->dropColumn(['discount_type', 'discount_value', 'discount_starts_at', 'discount_ends_at']);
        });
    }
};