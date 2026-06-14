<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SumarioProveedorDocumento extends Model
{
    use HasFactory;

    protected $fillable = [
        'sumario_id',
        'opcion_numero',
        'proveedor_id',
        'proveedor_nombre_snapshot',
        'archivo_path',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'subido_por_user_id',
    ];

    public function sumario(): BelongsTo
    {
        return $this->belongsTo(Sumario::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }
}