<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraItem extends Model
{
    use HasFactory;

    protected $table = 'orden_compra_items';

    protected $fillable = [
        'orden_compra_id',
        'sumario_item_id',
        'solicitud_compra_item_id',
        'item',
        'descripcion',
        'unidad_medida',
        'cantidad',
        'precio_unitario',
        'precio_total',
        'estado_recepcion',
        'en_transicion_at',
        'entregado_at',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'precio_total' => 'decimal:2',
        'en_transicion_at' => 'datetime',
        'entregado_at' => 'datetime',
    ];

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function sumarioItem(): BelongsTo
    {
        return $this->belongsTo(SumarioItem::class);
    }

    public function solicitudCompraItem(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompraItem::class);
    }
}
