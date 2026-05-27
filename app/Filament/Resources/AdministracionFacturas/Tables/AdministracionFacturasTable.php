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
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('correlativo_odc')
                    ->toggleable()
                    ->label('Correlativo ODC')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sumario.solicitudCompra.codigo_control')
                    ->toggleable()
                    ->label('Solicitud')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('proveedor.nombre')
                    ->toggleable()
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('factura_enviada_administracion_at')
                    ->toggleable()
                    ->label('Estado envio')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
                TextColumn::make('factura_numero')
                    ->toggleable()
                    ->label('Nro Factura')
                    ->default('-'),
                TextColumn::make('factura_monto_total')
                    ->toggleable()
                    ->label('Total factura')
                    ->formatStateUsing(fn ($state): string => filled($state)
                        ? '$ ' . number_format((float) $state, 2, ',', '.')
                        : '-')
                    ->placeholder('-'),
                TextColumn::make('factura_procesada_administracion_at')
                    ->toggleable()
                    ->label('Cargada en DB')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
            ])
            ->recordActions([
                OrdenesCompraTable::makeOpenFacturaRecepcionAction(),
                OrdenesCompraTable::makeOpenNotaEntregaRecepcionAction(),
                OrdenesCompraTable::makeAdministracionFacturaAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
