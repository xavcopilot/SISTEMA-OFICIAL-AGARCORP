<?php

namespace App\Filament\Resources\FacturasCompra\Tables;

use App\Filament\Resources\OrdenesCompra\Tables\OrdenesCompraTable;
use App\Models\Departamento;
use App\Models\User;
use App\Support\Filament\DatabaseNotificationSender;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FacturasCompraTable
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
                    ->badge()
                    ->state(fn ($record): string => filled($record->factura_enviada_administracion_at)
                        ? 'ENVIADA A ADMINISTRACION'
                        : 'PENDIENTE DE ENVIO')
                    ->color(fn ($record): string => filled($record->factura_enviada_administracion_at)
                        ? 'success'
                        : 'warning'),
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
                    ->label('Procesada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
            ])
            ->recordActions([
                OrdenesCompraTable::makeOpenFacturaImageAction(),
                Action::make('enviarFacturaAdministracion')
                    ->label('Enviar Factura a Administración')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->visible(fn ($record): bool => blank($record->factura_enviada_administracion_at))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->forceFill([
                            'factura_enviada_administracion_at' => now(),
                            'factura_enviada_por_user_id' => auth()->id(),
                            'workflow_post_compra' => 'FACTURA_ENVIADA_ADMINISTRACION',
                        ])->save();

                        self::notifyAdministracionInvoiceReady($record);

                        Notification::make()
                            ->title('Factura enviada a Administracion')
                            ->body('La factura se envio al modulo Administracion de Facturas para su carga en base de datos.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function notifyAdministracionInvoiceReady(mixed $record): void
    {
        $departamentoId = Departamento::query()
            ->whereIn('nombre', ['ADMINISTRACIÓN', 'ADMINISTRACION'])
            ->value('id');

        $users = User::query()
            ->when($departamentoId, fn ($query) => $query->where('departamento_id', $departamentoId))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($record): void {
            $notification = Notification::make()
                ->title('Factura pendiente de carga manual')
                ->body('La ODC ' . (string) $record->correlativo_odc . ' fue enviada por Finanzas para carga contable en Administracion de Facturas.')
                ->warning();

            DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
        });
    }
}
