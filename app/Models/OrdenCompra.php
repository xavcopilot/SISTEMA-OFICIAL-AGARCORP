<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    use HasFactory;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'sumario_id',
        'correlativo_odc',
        'proveedor_id',
        'rif_proveedor',
        'direccion_proveedor',
        'email_proveedor',
        'contacto_proveedor',
        'tasa_bcv',
        'condicion_pago',
        'monto_exento',
        'sub_total',
        'iva_16',
        'gastos_adicionales',
        'total_general',
        'estado',
        'tipo_documento_recepcion',
        'factura_path',
        'factura_pendiente',
        'recepcion_procesada_at',
        'recibido_por_user_id',
        'conformidad_solicitante_at',
        'conformidad_por_user_id',
        'inventario_movimiento_id',
        'factura_procesada_administracion_at',
    ];

    protected $casts = [
        'tasa_bcv' => 'decimal:6',
        'monto_exento' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'iva_16' => 'decimal:2',
        'gastos_adicionales' => 'decimal:2',
        'total_general' => 'decimal:2',
        'factura_pendiente' => 'boolean',
        'recepcion_procesada_at' => 'datetime',
        'conformidad_solicitante_at' => 'datetime',
        'factura_procesada_administracion_at' => 'datetime',
    ];

    public function sumario(): BelongsTo
    {
        return $this->belongsTo(Sumario::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por_user_id');
    }

    public function conformidadPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conformidad_por_user_id');
    }

    public function inventarioMovimiento(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventario_movimiento_id');
    }
}
