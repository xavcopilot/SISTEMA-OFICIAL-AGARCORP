<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'rif',
        'direccion',
        'ciudad',
        'email',
        'contacto',
        'telefono',
        'banco',
        'numero_cuenta',
        'tipo_documento',
        'documento',
        'beneficiario_nombre_apellido',
    ];

    public function sumariosGanados(): HasMany
    {
        return $this->hasMany(Sumario::class, 'proveedor_ganador_id');
    }

    public function opcionesSumario(): HasMany
    {
        return $this->hasMany(SumarioItemOpcion::class);
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class);
    }
}