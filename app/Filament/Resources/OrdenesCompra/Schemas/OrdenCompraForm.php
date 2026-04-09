<?php

namespace App\Filament\Resources\OrdenesCompra\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrdenCompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cabecera ODC (ADV-FPR-ODC)')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('correlativo_odc')
                                    ->label('Correlativo ODC')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                TextInput::make('sumario.correlativo_sdc')
                                    ->label('Correlativo SDC')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                TextInput::make('proveedor.nombre')
                                    ->label('Proveedor')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(6),

                                TextInput::make('rif_proveedor')
                                    ->label('RIF')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),

                                TextInput::make('direccion_proveedor')
                                    ->label('Direccion')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(5),

                                TextInput::make('email_proveedor')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('contacto_proveedor')
                                    ->label('Contacto')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('tasa_bcv')
                                    ->label('Tasa BCV')
                                    ->numeric()
                                    ->step('0.000001')
                                    ->columnSpan(3),

                                TextInput::make('condicion_pago')
                                    ->label('Condicion de pago')
                                    ->maxLength(255)
                                    ->columnSpan(5),

                                Select::make('estado')
                                    ->label('Estado')
                                    ->options([
                                        'PENDIENTE_APROBACION' => 'Pendiente Aprobacion',
                                        'PAGADA' => 'Pagada',
                                        'EN_ESPERA_DE_PRODUCTO' => 'En Espera de Producto',
                                        'RECIBIDA' => 'Recibida',
                                    ])
                                    ->required()
                                    ->columnSpan(4),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Items adjudicados al proveedor ganador')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        TextInput::make('item')
                                            ->label('Item')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(1),

                                        TextInput::make('descripcion')
                                            ->label('Descripcion')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(4),

                                        TextInput::make('unidad_medida')
                                            ->label('UND')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(1),

                                        TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario')
                                            ->label('Precio unitario')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(2),

                                        TextInput::make('precio_total')
                                            ->label('Precio total')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Calculos financieros')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('monto_exento')
                                    ->label('Monto exento')
                                    ->numeric()
                                    ->default(0)
                                    ->live(debounce: 250)
                                    ->afterStateHydrated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->columnSpan(3),

                                TextInput::make('sub_total')
                                    ->label('Sub total')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(3),

                                TextInput::make('iva_16')
                                    ->label('IVA 16%')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(2),

                                TextInput::make('gastos_adicionales')
                                    ->label('Gastos adicionales')
                                    ->numeric()
                                    ->default(0)
                                    ->live(debounce: 250)
                                    ->afterStateHydrated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->columnSpan(2),

                                TextInput::make('total_general')
                                    ->label('Total general')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Recepcion y Cierre')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('tipo_documento_recepcion')
                                    ->label('Documento de recepcion')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                TextInput::make('recepcion_procesada_at')
                                    ->label('Recepcion procesada en')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                TextInput::make('conformidad_solicitante_at')
                                    ->label('Conformidad solicitante')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                TextInput::make('inventarioMovimiento.nro_control')
                                    ->label('Entrada oficial inventario')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                Placeholder::make('factura_path_preview')
                                    ->label('Factura cargada')
                                    ->content(fn ($record): string => filled($record?->factura_path) ? (string) $record->factura_path : 'No cargada')
                                    ->columnSpan(8),

                                Placeholder::make('alerta_factura')
                                    ->label('Alerta')
                                    ->content(fn ($record): string => (bool) ($record?->factura_pendiente ?? false)
                                        ? 'FACTURA PENDIENTE: recibido con Nota de Entrega.'
                                        : 'Sin alertas de factura pendiente.')
                                    ->columnSpan(4),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function recalculateTotals(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];

        $subTotal = collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->sum(fn (array $item): float => (float) ($item['precio_total'] ?? 0));

        $subTotal = round((float) $subTotal, 2);
        $iva = round($subTotal * 0.16, 2);
        $montoExento = round((float) ($get('monto_exento') ?? 0), 2);
        $gastosAdicionales = round((float) ($get('gastos_adicionales') ?? 0), 2);
        $totalGeneral = round($subTotal + $iva + $montoExento + $gastosAdicionales, 2);

        $set('sub_total', $subTotal);
        $set('iva_16', $iva);
        $set('total_general', $totalGeneral);
    }
}
