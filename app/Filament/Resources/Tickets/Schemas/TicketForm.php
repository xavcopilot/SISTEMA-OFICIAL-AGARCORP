<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Models\Ticket;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Usamos Hidden para que el ID no estorbe visualmente
                Hidden::make('user_id')
                    ->default(fn () => auth()->id())
                    ->required(),

                TextInput::make('nombre_solicitante')
                    ->label('Nombre y apellido')
                    ->default(fn () => auth()->user()->name)
                    ->readonly() 
                    ->required(),

                Select::make('departamento')
                    ->label('Departamento')
                    ->required()
                    ->options(fn () => \App\Models\Departamento::pluck('nombre','nombre')->toArray())
                    ->default(fn () => auth()->user()->departamento?->nombre)
                    ->required(),

 // 1. Este componente SOLO se ve cuando estás consultando (view)
\Filament\Forms\Components\Placeholder::make('tipo_solicitud_vista')
    ->label('¿Qué tipo de solicitud deseas realizar?')
    ->content(fn ($record) => $record?->tipo_solicitud_label)
    ->visible(fn ($operation) => $operation === 'view')
    ->extraAttributes([
        'class' => 'p-2 bg-gray-50 border border-gray-300 rounded-lg shadow-sm text-sm text-gray-600',
        'style' => 'min-height: 38px; display: flex; align-items: center;'
    ]),

// 2. Este es tu Select de siempre, pero SOLO se ve al crear o editar
Select::make('tipo_solicitud')
    ->label('¿Qué tipo de solicitud deseas realizar?')
    ->options(Ticket::TIPO_SOLICITUD_LABELS)
    ->dehydrateStateUsing(fn (?string $state) => Ticket::normalizeTipoSolicitud($state))
    ->required()
    ->live()
    ->hidden(fn ($operation) => $operation === 'view'),

                // --- SECCIÓN SOPORTE IT ---
                
          

                Select::make('nivel_urgencia')
                    ->label('Nivel de urgencia')
                    ->options([
                        'Alta' => 'Alta',
                        'Media' => 'Media',
                        'Baja' => 'Baja',
                    ])
                    ->visible(fn ($get, $state) => $get('tipo_solicitud') === Ticket::TIPO_SOLICITUD_SOPORTE_IT || filled($state)),

                TextInput::make('equipo_afectado')
                    ->label('Equipo afectado')
                    ->placeholder('Ej: Laptop Dell, Impresora técnica...')
                    ->visible(fn ($get, $state) => $get('tipo_solicitud') === Ticket::TIPO_SOLICITUD_SOPORTE_IT || filled($state)),

                Textarea::make('descripcion_problema')
                    ->label('Descripción del problema')
                    ->placeholder('Describe brevemente la falla...')
                    ->rows(3)
                    ->visible(fn ($get, $state) => $get('tipo_solicitud') === Ticket::TIPO_SOLICITUD_SOPORTE_IT || filled($state)),


                 

               
                // --- SECCIÓN CAMBIO DE TÓNER ---

                // ahora sacamos las impresoras directamente de la tabla para que
                // cualquiera pueda agregarlas desde aquí (CreateOption) y no haya que
                // mantener el listado a mano.
                Select::make('codigo_impresora')
                    ->label('Seleccione la impresora')
                    ->options(fn () => \App\Models\Impresora::query()
                        ->pluck('nombre', 'codigo')
                        ->toArray())
                    ->searchable()
                    ->createOptionForm([
                        \Filament\Forms\Components\TextInput::make('codigo')
                            ->label('Código de equipo')
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('nombre')
                            ->label('Nombre / Ubicación')
                            ->required(),
                    ])
                    ->visible(fn ($get, $state) => $get('tipo_solicitud') === Ticket::TIPO_SOLICITUD_CAMBIO_TONER || filled($state)),

                Select::make('color_toner')
                    ->label('Color de tóner requerido')
                    ->options([
                        'NEGRO' => 'Negro',
                        'CYAN' => 'Cian',
                        'YELLOW' => 'Amarillo',
                        'MAGENTA' => 'Magenta',
                    ])
                    ->visible(fn ($get, $state) => $get('tipo_solicitud') === Ticket::TIPO_SOLICITUD_CAMBIO_TONER || filled($state)),

Select::make('estado')
    ->label('Estado de la Solicitud')
    ->options([
        'Abierto' => 'Abierto',
        'En Proceso' => 'En Proceso',
        'Resuelto' => 'Resuelto',
        'Cancelado' => 'Cancelado',
    ])
    // LA CLAVE: Solo es "disabled" si no eres gestor
    ->disabled(fn () => !auth()->user()->hasRole(['admin', 'Alta Gerencia', 'A.I.T']))
    // Pero es visible para todos en modo vista/edición
    ->visible(fn ($operation) => $operation === 'edit' || $operation === 'view')
    ->required()
    ->native(false),

            ]);
            
    }
}