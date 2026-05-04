<?php

namespace App\Filament\Resources\AdministracionFacturas\Tables;

use App\Filament\Resources\OrdenesCompra\Tables\OrdenesCompraTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdministracionFacturasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('correlativo_odc')
                    ->label('Correlativo ODC')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sumario.solicitudCompra.codigo_control')
                    ->label('Solicitud')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('factura_path')
                    ->label('Factura recibida')
                    ->state(fn ($record): string => filled($record->factura_path) ? 'Descargar factura' : 'Sin factura')
                    ->url(fn ($record): ?string => filled($record->factura_path)
                        ? route('ordenes-compra.documento-recepcion.download', ['ordenCompra' => $record])
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('factura_enviada_administracion_at')
                    ->label('Estado envio')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
                TextColumn::make('factura_numero')
                    ->label('Nro Factura')
                    ->default('-'),
                TextColumn::make('factura_monto_total')
                    ->label('Total factura')
                    ->formatStateUsing(fn ($state): string => filled($state)
                        ? '$ ' . number_format((float) $state, 2, ',', '.')
                        : '-')
                    ->placeholder('-'),
                TextColumn::make('factura_procesada_administracion_at')
                    ->label('Cargada en DB')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
            ])
            ->recordActions([
                OrdenesCompraTable::makeOpenFacturaImageAction(),
                OrdenesCompraTable::makeAdministracionFacturaAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
