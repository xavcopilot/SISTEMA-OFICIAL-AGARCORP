<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyWithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'destination',
        'requires_return',
        'return_date',
        'status',
        'requested_at',
    ];

    protected $casts = [
        'requires_return' => 'boolean',
        'return_date' => 'datetime',
        'requested_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyWithdrawal::class, 'daily_withdrawal_request_id');
    }
}
