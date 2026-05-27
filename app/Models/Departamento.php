<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $fillable = ['nombre'];

    public $timestamps = false;

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
