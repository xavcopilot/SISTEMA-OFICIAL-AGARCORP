<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SumarioItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sumario_id',
        'solicitud_compra_item_id',
        'item',
        'descripcion',
        'unidad_medida',
        'cantidad',
        'validacion_gerencia_resultado',
        'validacion_gerencia_comentario',
        'sub_estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    public function sumario(): BelongsTo
    {
        return $this->belongsTo(Sumario::class);
    }

    public function solicitudCompraItem(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompraItem::class);
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(SumarioItemOpcion::class);
    }

    public function ordenCompraItems(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }
}
