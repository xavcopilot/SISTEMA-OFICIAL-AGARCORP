<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    public const TIPO_SOLICITUD_SOPORTE_IT = 'SOPORTE_IT';
    public const TIPO_SOLICITUD_CAMBIO_TONER = 'CAMBIO_TONER';

    public const TIPO_SOLICITUD_LABELS = [
        self::TIPO_SOLICITUD_SOPORTE_IT => 'Soporte IT',
        self::TIPO_SOLICITUD_CAMBIO_TONER => 'Cambio de toner',
    ];

    protected $fillable = [
        'user_id', 
        'nombre_solicitante', 
        'departamento', 
        'tipo_solicitud',
        'tipo_problema', 
        'nivel_urgencia', 
        'equipo_afectado', 
        'descripcion_problema', 
        'prioridad_estrellas',
        'codigo_impresora', 
        'color_toner', 
        'estado', 
        'comentarios_ait'
    ];

    // Relación: Un ticket pertenece a un usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function normalizeTipoSolicitud(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($value) {
            'SOPORTE IT', 'Soporte IT', self::TIPO_SOLICITUD_SOPORTE_IT => self::TIPO_SOLICITUD_SOPORTE_IT,
            'CAMBIO DE TONER', 'Cambio de Toner', self::TIPO_SOLICITUD_CAMBIO_TONER => self::TIPO_SOLICITUD_CAMBIO_TONER,
            default => $value,
        };
    }

    public function getTipoSolicitudLabelAttribute(): string
    {
        return self::TIPO_SOLICITUD_LABELS[$this->tipo_solicitud] ?? (string) $this->tipo_solicitud;
    }
}