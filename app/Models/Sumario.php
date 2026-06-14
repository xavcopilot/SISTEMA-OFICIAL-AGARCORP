<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sumario extends Model
{
    use HasFactory;

    protected $fillable = [
        'solicitud_compra_id',
        'correlativo_sdc',
        'fecha',
        'procedencia',
        'tipo_orden',
        'departamento_solicitante',
        'total_compra_prov1',
        'total_compra_prov2',
        'total_compra_prov3',
        'condiciones_pago',
        'tiempo_entrega',
        'prioridad',
        'proveedor_ganador_id',
        'observaciones',
        'elaborado_por_user_id',
        'revisado_por_user_id',
        'estado',
        'workflow_estado',
        'enviado_validacion_finanzas_at',
        'enviado_por_user_id',
        'validado_finanzas_at',
        'validado_por_user_id',
        'validacion_finanzas_resultado',
        'validacion_finanzas_comentario',
        'decision_gerencia_finanzas_at',
        'decision_gerencia_por_user_id',
        'decision_gerencia_resultado',
        'decision_gerencia_comentario',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_compra_prov1' => 'decimal:2',
        'total_compra_prov2' => 'decimal:2',
        'total_compra_prov3' => 'decimal:2',
        'enviado_validacion_finanzas_at' => 'datetime',
        'validado_finanzas_at' => 'datetime',
        'decision_gerencia_finanzas_at' => 'datetime',
    ];

    public function solicitudCompra(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompra::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SumarioItem::class);
    }

    public function providerDocuments(): HasMany
    {
        return $this->hasMany(SumarioProveedorDocumento::class)
            ->orderBy('opcion_numero')
            ->orderBy('id');
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class);
    }

    public function proveedorGanador(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_ganador_id');
    }

    public function elaboradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'elaborado_por_user_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_user_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_user_id');
    }

    public function decisionGerenciaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_gerencia_por_user_id');
    }
}
