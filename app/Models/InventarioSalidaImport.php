<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioSalidaImport extends Model
{
    use HasFactory;

    protected $table = 'inventario_salidas_imports';

    protected $fillable = [
        'N° CONTROL',
        'FECHA',
        'MES',
        'RESPONSABLE',
        'AREA/DPTO',
        'QUIEN ENTREGA',
        'SKU',
        'DESCRIPCION',
        'MARCA',
        'CATEGORIA',
        'SUBCAT',
        'SERIAL',
        'ESTADO',
        'MEDIDA',
        'CANT',
        'UBICACION',
        'RETORNA',
        'OBSERVACIONES',
        'inventory_movement_id',
        'movement_item_id',
        'product_id',
        'lote_importacion',
        'procesado',
        'procesado_en',
        'error_importacion',
        'datos_originales',
    ];

    protected $casts = [
        'FECHA' => 'date',
        'CANT' => 'integer',
        'inventory_movement_id' => 'integer',
        'movement_item_id' => 'integer',
        'product_id' => 'integer',
        'procesado' => 'boolean',
        'procesado_en' => 'datetime',
        'datos_originales' => 'array',
    ];

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    public function movementItem(): BelongsTo
    {
        return $this->belongsTo(MovementItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}