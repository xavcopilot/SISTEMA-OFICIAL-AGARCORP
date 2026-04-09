<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudCompra extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo_control',
        'codigo_control_procura',
        'fecha_solicitud',
        'tipo_solicitud',
        'prioridad',
        'departamento_solicitante',
        'para_ser_usado_en',
        'centro',
        'elemento',
        'cuenta',
        'contrato',
        'solicitado_por_user_id',
        'por_almacen_user_id',
        'aprobado_por_user_id',
        'recibido_por_user_id',
        'cargo_solicitante',
        'cargo_almacen',
        'cargo_aprobador',
        'cargo_receptor',
        'firma_solicitante',
        'firma_almacen',
        'firma_aprobador',
        'firma_receptor',
        'fecha_solicitante',
        'fecha_almacen',
        'fecha_aprobador',
        'fecha_receptor',
        'hora_receptor',
        'rechazo_etapa',
        'rechazo_comentario',
        'rechazo_por_user_id',
        'rechazo_destinatario_user_id',
        'rechazo_en',
        'recepcion_conforme',
        'estado',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_solicitante' => 'date',
        'fecha_almacen' => 'date',
        'fecha_aprobador' => 'date',
        'fecha_receptor' => 'date',
        'rechazo_en' => 'datetime',
        'recepcion_conforme' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SolicitudCompraItem::class);
    }

    public function sumarios(): HasMany
    {
        return $this->hasMany(Sumario::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }

    public function porAlmacen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'por_almacen_user_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_user_id');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por_user_id');
    }

    public function rechazoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazo_por_user_id');
    }

    public function rechazoDestinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazo_destinatario_user_id');
    }
}
