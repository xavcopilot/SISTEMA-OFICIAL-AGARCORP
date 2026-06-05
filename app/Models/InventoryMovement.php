<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryMovement extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (InventoryMovement $movement): void {
            $movement->fecha = now()->toDateString();

            if (empty($movement->nro_control)) {
                $movement->nro_control = self::generateControlNumber((string) $movement->tipo);
            }

            if (auth()->check()) {
                $movement->created_by_user_id = auth()->id();
            }
        });

        static::updating(function (InventoryMovement $movement): void {
            // Mantiene inmutables campos sensibles una vez creado el movimiento.
            $movement->fecha = $movement->getOriginal('fecha');
            $movement->nro_control = $movement->getOriginal('nro_control');
            $movement->tipo = $movement->getOriginal('tipo');
            $movement->created_by_user_id = $movement->getOriginal('created_by_user_id');

            if (auth()->check()) {
                $movement->updated_by_user_id = auth()->id();
            }
        });
    }

    protected $fillable = [
        'tipo',
        'nro_control',
        'almacenista_user_id',
        'entregado_por_user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'orden_compra',
        'nro_solicitud',
        'factura_nota',
        'nro_doc_legal',
        'proveedor',
        'entregado_por',
        'almacenista',
        'dpto_responsable',
        'responsable_destino',
        'dpto_destino',
        'comentarios',
        'solicitar_formato_entrada',
        'total_items',
    ];

    protected $casts = [
        'almacenista_user_id' => 'integer',
        'entregado_por_user_id' => 'integer',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
        'fecha' => 'date',
        'total_items' => 'integer',
        'solicitar_formato_entrada' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MovementItem::class, 'movement_id');
    }

    public function almacenistaUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'almacenista_user_id');
    }

    public function entregadoPorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function getDptoResponsableUnificadoAttribute(): ?string
    {
        foreach (['dpto_responsable', 'dpto_destino', 'responsable_destino'] as $field) {
            $value = trim((string) ($this->getAttribute($field) ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public static function generateControlNumber(string $tipo): string
    {
        $prefix = match ($tipo) {
            'ingreso' => 'ING',
            'entrada' => 'EN',
            default => 'SAL',
        };

        return sprintf('%s-%s', $prefix, now()->format('Ymd-His'));
    }
}
