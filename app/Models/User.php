<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Departamento;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'firma_password',
        'withdrawal_password',
        'departamento_id',
        'cargo_id',
    ];

    protected $hidden = [
        'password',
        'firma_password',
        'withdrawal_password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'firma_password' => 'hashed',
            'withdrawal_password' => 'hashed',
        ];
    }

    // Relación con el departamento (tabla independiente)
    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    // Esta función permite que los usuarios entren al panel administrativo
    public function canAccessPanel(Panel $panel): bool
    {
        // Por ahora, permitimos que cualquier usuario registrado entre.
        // Más adelante podemos hacerlo más estricto.
        return true; 
    }
}