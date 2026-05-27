<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_id',
        'product_id',
        'cantidad',
        'precio_momento',
        'retorna',
        'observaciones_item',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_momento' => 'decimal:2',
        'retorna' => 'boolean',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
