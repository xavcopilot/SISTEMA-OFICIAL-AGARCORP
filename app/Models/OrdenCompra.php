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
        'sitio_entrega',
        'comentarios',
        'elaborado_por_user_id',
        'elaborado_firmado_at',
        'aprobado_por_user_id',
        'aprobado_firmado_at',
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
        'nota_entrega_path',
        'factura_numero',
        'factura_numero_control',
        'factura_fecha_emision',
        'factura_base_imponible',
        'factura_monto_iva',
        'factura_monto_total',
        'retencion_iva_monto',
        'retencion_islr_monto',
        'comprobantes_retencion_paths',
        'observacion_administracion',
        'factura_enviada_administracion_at',
        'factura_enviada_por_user_id',
        'factura_cargada_administracion_at',
        'factura_cargada_por_user_id',
        'factura_pendiente',
        'recepcion_procesada_at',
        'recibido_por_user_id',
        'conformidad_solicitante_at',
        'conformidad_por_user_id',
        'visible_conformidades_procura',
        'devolucion_solicitada_at',
        'devolucion_solicitada_por_user_id',
        'devolucion_motivo',
        'inventario_movimiento_id',
        'factura_procesada_administracion_at',
        'rechazo_etapa',
        'rechazo_comentario',
        'rechazo_por_user_id',
        'rechazo_en',
    ];

    protected $casts = [
        'tasa_bcv' => 'decimal:6',
        'monto_exento' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'iva_16' => 'decimal:2',
        'gastos_adicionales' => 'decimal:2',
        'total_general' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'factura_base_imponible' => 'decimal:2',
        'factura_monto_iva' => 'decimal:2',
        'factura_monto_total' => 'decimal:2',
        'retencion_iva_monto' => 'decimal:2',
        'retencion_islr_monto' => 'decimal:2',
        'comprobantes_retencion_paths' => 'array',
        'factura_pendiente' => 'boolean',
        'visible_conformidades_procura' => 'boolean',
        'factura_fecha_emision' => 'date',
        'pago_registrado_at' => 'datetime',
        'confirmado_procura_at' => 'datetime',
        'factura_enviada_administracion_at' => 'datetime',
        'factura_cargada_administracion_at' => 'datetime',
        'recepcion_procesada_at' => 'datetime',
        'conformidad_solicitante_at' => 'datetime',
        'devolucion_solicitada_at' => 'datetime',
        'factura_procesada_administracion_at' => 'datetime',
        'elaborado_firmado_at' => 'datetime',
        'aprobado_firmado_at' => 'datetime',
        'rechazo_en' => 'datetime',
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

    public function comprobantes(): HasMany
    {
        return $this->hasMany(OrdenCompraComprobante::class);
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

    public function facturaCargadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'factura_cargada_por_user_id');
    }

    public function elaboradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'elaborado_por_user_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_user_id');
    }

    public function devolucionSolicitadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devolucion_solicitada_por_user_id');
    }

    public function inventarioMovimiento(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventario_movimiento_id');
    }

    public function facturaRecepcionPath(): ?string
    {
        $path = trim((string) ($this->factura_path ?? ''));

        if ($path === '') {
            return null;
        }

        if ((string) ($this->tipo_documento_recepcion ?? '') === 'NOTA' && blank($this->nota_entrega_path)) {
            return null;
        }

        return $path;
    }

    public function notaEntregaRecepcionPath(): ?string
    {
        $path = trim((string) ($this->nota_entrega_path ?? ''));

        if ($path !== '') {
            return $path;
        }

        if ((string) ($this->tipo_documento_recepcion ?? '') !== 'NOTA') {
            return null;
        }

        $legacyPath = trim((string) ($this->factura_path ?? ''));

        return $legacyPath !== '' ? $legacyPath : null;
    }

    public function hasFacturaRecepcion(): bool
    {
        return $this->facturaRecepcionPath() !== null;
    }

    public function hasNotaEntregaRecepcion(): bool
    {
        return $this->notaEntregaRecepcionPath() !== null;
    }

    public function hasFacturaRecepcionOnDisk(): bool
    {
        $path = $this->facturaRecepcionPath();

        if ($path === null) {
            return false;
        }

        return \Illuminate\Support\Facades\Storage::disk('odc_facturas')->exists($path);
    }

    public function hasNotaEntregaRecepcionOnDisk(): bool
    {
        $path = $this->notaEntregaRecepcionPath();

        if ($path === null) {
            return false;
        }

        $disk = (string) ($this->tipo_documento_recepcion ?? '') === 'NOTA'
            ? 'odc_notas_entrega'
            : 'odc_facturas';

        return \Illuminate\Support\Facades\Storage::disk($disk)->exists($path);
    }

    public function hasComprobanteOnDisk(): bool
    {
        $path = trim((string) ($this->comprobante_pago_path ?? ''));

        if ($path === '') {
            return false;
        }

        return \Illuminate\Support\Facades\Storage::disk('odc_comprobantes')->exists($path);
    }

    public function rechazoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazo_por_user_id');
    }
}
