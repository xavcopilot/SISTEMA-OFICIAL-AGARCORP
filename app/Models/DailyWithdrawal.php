<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'destination',
        'requires_return',
        'return_date',
        'status',
        'rejection_reason',
        'warehouse_user_id',
        'requested_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'requires_return' => 'boolean',
        'return_date' => 'datetime',
        'requested_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouseUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pendiente');
    }
}
