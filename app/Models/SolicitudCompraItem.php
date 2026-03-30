<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCompraItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'solicitud_compra_id',
        'item',
        'descripcion',
        'unidad_medida',
        'cantidad_solicitada',
        'cantidad_existencia',
        'cantidad_a_comprar',
    ];

    public function solicitudCompra(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompra::class);
    }
}
