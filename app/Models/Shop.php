<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    //
    use SoftDeletes;
    
    $fillable = [
        'vendor_id',
        'name',
        'address',
        'country',
        'state',
        'city',
        'zipcode',
        'logo',
    ];

}
