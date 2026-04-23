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
                    ->label('Imagen recibida')
                    ->default('Sin factura'),
                TextColumn::make('factura_numero')
                    ->label('Nro Factura')
                    ->default('-'),
                TextColumn::make('factura_monto_total')
                    ->label('Total factura')
                    ->money('VES')
                    ->placeholder('-'),
                TextColumn::make('factura_procesada_administracion_at')
                    ->label('Procesada')
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
