<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminInvite extends Model
{
    protected $fillable = [
        'email',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // Look up a still-usable invite from the raw token
    public static function findValid(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}