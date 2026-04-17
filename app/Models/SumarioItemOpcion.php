<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SumarioItemOpcion extends Model
{
    use HasFactory;

    protected $table = 'sumario_item_opciones';

    protected $fillable = [
        'sumario_item_id',
        'opcion_numero',
        'proveedor_id',
        'proveedor_nombre',
        'marca',
        'precio_unitario',
        'precio_total',
        'seleccionada',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'precio_total' => 'decimal:2',
        'seleccionada' => 'boolean',
    ];

    public function sumarioItem(): BelongsTo
    {
        return $this->belongsTo(SumarioItem::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
