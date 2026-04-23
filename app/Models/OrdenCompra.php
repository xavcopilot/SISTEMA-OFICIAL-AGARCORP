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
        'departamento_solicitante',
        'monto_exento',
        'sub_total',
        'iva_16',
        'gastos_adicionales',
        'total_general',
        'estado',
        'workflow_post_compra',
        'pago_registrado_at',
        'pago_por_user_id',
        'comprobante_pago_path',
        'referencia_pago',
        'monto_pagado',
        'observacion_pago',
        'confirmado_procura_at',
        'confirmado_por_user_id',
        'tipo_documento_recepcion',
        'factura_path',
        'factura_enviada_administracion_at',
        'factura_enviada_por_user_id',
        'factura_pendiente',
        'recepcion_procesada_at',
        'recibido_por_user_id',
        'conformidad_solicitante_at',
        'conformidad_por_user_id',
        'devolucion_solicitada_at',
        'devolucion_solicitada_por_user_id',
        'devolucion_motivo',
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
        'monto_pagado' => 'decimal:2',
        'factura_pendiente' => 'boolean',
        'pago_registrado_at' => 'datetime',
        'confirmado_procura_at' => 'datetime',
        'factura_enviada_administracion_at' => 'datetime',
        'recepcion_procesada_at' => 'datetime',
        'conformidad_solicitante_at' => 'datetime',
        'devolucion_solicitada_at' => 'datetime',
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

    public function pagoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_por_user_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por_user_id');
    }

    public function facturaEnviadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'factura_enviada_por_user_id');
    }

    public function devolucionSolicitadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devolucion_solicitada_por_user_id');
    }

    public function inventarioMovimiento(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventario_movimiento_id');
    }
}
