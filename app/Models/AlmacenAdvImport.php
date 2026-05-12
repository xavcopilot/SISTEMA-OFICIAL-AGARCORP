<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlmacenAdvImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'SKU',
        'PRODUCTO',
        'MARCA',
        'CATEGORIA',
        'SUBCATG',
        'ESTADO',
        'MEDIDA',
        'SERIAL',
        'ALMACEN',
        'UBICACION',
        'RESPONSABLE',
        'MIN',
        'STATUS (1,2,3)',
        'CANT_TOTAL',
        'ENTRADAS',
        'SALIDAS',
        'P_UNITARIO',
        'P_TOTAL',
        'FECHA DE ADQUISICION',
        'FECHA DE ULTIMA ENTRADA',
        'FECHA DE ULTIMA SALIDA',
        'ESTADO REGISTRO',
        'product_id',
        'lote_importacion',
        'procesado',
        'procesado_en',
        'error_importacion',
        'datos_originales',
    ];

    protected $casts = [
        'MIN' => 'integer',
        'STATUS (1,2,3)' => 'integer',
        'CANT_TOTAL' => 'integer',
        'ENTRADAS' => 'integer',
        'SALIDAS' => 'integer',
        'P_UNITARIO' => 'decimal:2',
        'P_TOTAL' => 'decimal:2',
        'FECHA DE ADQUISICION' => 'date',
        'FECHA DE ULTIMA ENTRADA' => 'date',
        'FECHA DE ULTIMA SALIDA' => 'date',
        'product_id' => 'integer',
        'procesado' => 'boolean',
        'procesado_en' => 'datetime',
        'datos_originales' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}