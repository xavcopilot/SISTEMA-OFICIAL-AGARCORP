<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Filament\Pages\Page;
use App\Filament\Resources\Users\Pages\CreateUser;
use Filament\Forms\Components\Select;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre Completo')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Contraseña')
                    ->password() // Oculta los caracteres
                    ->revealable() // Permite verla con el icono del ojo
                    ->helperText('Se usa para iniciar sesion en el sistema.')
                    // Obligatoria solo al crear, opcional al editar
                    ->required(fn (Page $livewire): bool => $livewire instanceof CreateUser)
                    ->maxLength(255)
                    // Regla de seguridad: no guarda si llega vacío (para edición)
                    ->dehydrated(fn ($state) => filled($state))
                    // Encriptación automática antes de guardar
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                TextInput::make('firma_password')
                    ->label('Contraseña de firma')
                    ->password()
                    ->revealable()
                    ->helperText('Se usa para firmar/enviar solicitudes. Es independiente de la contraseña de inicio de sesión.')
                    ->required(fn (Page $livewire): bool => $livewire instanceof CreateUser)
                    ->maxLength(255)
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                TextInput::make('withdrawal_password')
                    ->label('Contraseña de retiros')
                    ->password()
                    ->revealable()
                    ->helperText('Clave rapida para retiros diarios de almacen. Debe tener entre 4 y 6 digitos.')
                    ->required(fn (Page $livewire): bool => $livewire instanceof CreateUser)
                    ->minLength(4)
                    ->maxLength(6)
                    ->rule('regex:/^\d{4,6}$/')
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                    // --- NUEVO CAMPO DE ROLES ---
                Select::make('roles')
                    ->label('Roles / Permisos')
                    ->multiple() // Permite asignar más de un rol si fuera necesario
                    ->relationship('roles', 'name') // Conecta automáticamente con Shield
                    ->preload() // Carga la lista rápido
                    ->searchable() // Por si llegas a tener muchos roles
                    ->required(), // Obliga a que cada usuario tenga al menos un rol

                Select::make('departamento_id')
                    ->label('Departamento')
                    ->relationship('departamento', 'nombre')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn ($record) => $record?->departamento_id ?? null),

                Select::make('cargo_id')
                    ->label('Cargo')
                    ->relationship('cargo', 'nombre')
                    ->searchable()
                    ->preload()
                    ->default(fn ($record) => $record?->cargo_id ?? null),
            ]);
    }
}