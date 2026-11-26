<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Keys match what the React storefront already uses (product_image, desc, merchant, verified).
     * selling_price is the price right now (after any active discount).
     */
    public function toArray(Request $request): array
    {
        $price = $this->effective_price;
        $status = $this->discountStatus();

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'product_image' => $this->image ? asset('storage/' . $this->image) : null,
            'initial_price' => $this->initial_price,
            'selling_price' => $price,
            'discount_percent' => $this->initial_price > 0 && $price < $this->initial_price
                ? (int) round((($this->initial_price - $price) / $this->initial_price) * 100)
                : 0,
            // The configured discount (also shown when scheduled or ended, so vendors can edit it)
            'discount' => $status ? [
                'type' => $this->discount_type,
                'value' => $this->discount_value,
                'starts_at' => $this->discount_starts_at,
                'ends_at' => $this->discount_ends_at,
                'status' => $status,
            ] : null,
            'stock' => $this->stock,
            'desc' => $this->description,
            'merchant' => $this->shop?->name,
            'verified' => (bool) $this->shop?->is_verified,
            'created_at' => $this->created_at,
        ];
    }
}