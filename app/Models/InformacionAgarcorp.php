<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformacionAgarcorp extends Model
{
    protected $table = 'informacion_impresa';

    protected $fillable = [
        'razon_social',
        'rif',
        'direccion_fiscal',
        'telefono_principal',
    ];

    public static function current(): self
    {
        $record = static::query()->first();

        if ($record) {
            return $record;
        }

        return static::query()->create([
            'razon_social' => config('app.name', 'AGARCORP'),
            'rif' => '',
            'direccion_fiscal' => '',
            'telefono_principal' => '',
        ]);
    }
}
