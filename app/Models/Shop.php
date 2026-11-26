<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    //
    use SoftDeletes;
    
    protected $fillable = [
        'vendor_id',
        'name',
        'address',
        'country',
        'state',
        'city',
        'zipcode',
        'logo',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}