<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudCompraItem extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (SolicitudCompraItem $item): void {
            $item->cantidad_pedida = round((float) ($item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);
        });
    }

    protected $fillable = [
        'solicitud_compra_id',
        'item',
        'descripcion',
        'unidad_medida',
        'cantidad_solicitada',
        'cantidad_existencia',
        'cantidad_a_comprar',
        'cantidad_pedida',
        'cantidad_en_sumario',
        'cantidad_comprada',
        'estado_item',
    ];

    protected $casts = [
        'cantidad_solicitada' => 'decimal:2',
        'cantidad_existencia' => 'decimal:2',
        'cantidad_a_comprar' => 'decimal:2',
        'cantidad_pedida' => 'decimal:2',
        'cantidad_en_sumario' => 'decimal:2',
        'cantidad_comprada' => 'decimal:2',
    ];

    public function solicitudCompra(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompra::class);
    }

    public function sumarioItems(): HasMany
    {
        return $this->hasMany(SumarioItem::class);
    }

    public function ordenCompraItems(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }
}
