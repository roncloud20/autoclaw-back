<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shop_id',
        'name',
        'brand',
        'category',
        'description',
        'initial_price',
        'discount_type',
        'discount_value',
        'discount_starts_at',
        'discount_ends_at',
        'stock',
        'image',
    ];

    protected $casts = [
        'initial_price' => 'integer',
        'discount_value' => 'integer',
        'discount_starts_at' => 'datetime',
        'discount_ends_at' => 'datetime',
        'stock' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // Admins manage everything; vendors only products in their own shops
    public function canBeManagedBy(User $user): bool
    {
        return $user->role === 'admin' || $this->shop?->vendor_id === $user->id;
    }

    // null = no discount set, otherwise scheduled | active | expired
    public function discountStatus(): ?string
    {
        if (!$this->discount_type || !$this->discount_value) {
            return null;
        }

        $now = now();

        if ($this->discount_starts_at && $this->discount_starts_at->gt($now)) {
            return 'scheduled';
        }

        if ($this->discount_ends_at && $this->discount_ends_at->lte($now)) {
            return 'expired';
        }

        return 'active';
    }

    // The price a customer pays right now
    public function getEffectivePriceAttribute(): int
    {
        if ($this->discountStatus() !== 'active') {
            return $this->initial_price;
        }

        if ($this->discount_type === 'percent') {
            return (int) round($this->initial_price * (100 - $this->discount_value) / 100);
        }

        return max($this->initial_price - $this->discount_value, 0);
    }

    /*
    |--------------------------------------------------------------------------
    | SQL versions of the same rules, for filtering and sorting by price
    |--------------------------------------------------------------------------
    */

    // [condition, bindings] for "has a discount running right now"
    public static function activeDiscountSql(): array
    {
        $now = now()->toDateTimeString();

        $sql = 'products.discount_type IS NOT NULL
            AND products.discount_value IS NOT NULL
            AND (products.discount_starts_at IS NULL OR products.discount_starts_at <= ?)
            AND (products.discount_ends_at IS NULL OR products.discount_ends_at > ?)';

        return [$sql, [$now, $now]];
    }

    // [expression, bindings] for the price a customer pays right now
    public static function effectivePriceSql(): array
    {
        [$active, $bindings] = static::activeDiscountSql();

        $sql = "CASE WHEN ($active) THEN (
                CASE products.discount_type
                    WHEN 'percent' THEN ROUND(products.initial_price * (100 - products.discount_value) / 100.0)
                    WHEN 'fixed' THEN (CASE WHEN products.initial_price > products.discount_value
                        THEN products.initial_price - products.discount_value ELSE 0 END)
                    ELSE products.initial_price
                END
            ) ELSE products.initial_price END";

        return [$sql, $bindings];
    }

    public function scopeOnSale($query)
    {
        [$sql, $bindings] = static::activeDiscountSql();

        return $query->whereRaw("($sql)", $bindings);
    }

    public function scopeWhereEffectivePrice($query, string $operator, int $value)
    {
        $operator = $operator === '<=' ? '<=' : '>=';
        [$sql, $bindings] = static::effectivePriceSql();

        return $query->whereRaw("($sql) $operator ?", [...$bindings, $value]);
    }

    public function scopeOrderByEffectivePrice($query, string $direction = 'asc')
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        [$sql, $bindings] = static::effectivePriceSql();

        return $query->orderByRaw("($sql) $direction", $bindings);
    }
}