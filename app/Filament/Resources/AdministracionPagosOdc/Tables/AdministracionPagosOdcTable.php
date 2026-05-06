<?php

namespace App\Filament\Resources\AdministracionPagosOdc\Tables;

use App\Models\OrdenCompraComprobante;
use App\Models\User;
use App\Support\Filament\DatabaseNotificationSender;
use App\Support\OdcModalSummaryRenderer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class AdministracionPagosOdcTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('correlativo_odc')
                    ->label('N° Control ODC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sumario.correlativo_sdc')
                    ->label('N° SDC Asociado')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('solicitud_codigo_control')
                    ->label('N° Solicitud Asociada')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->codigo_control ?: '-'))
                    ->searchable(),

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('total_general')
                    ->label('Total general')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record): string => (string) ($record->workflow_post_compra === 'PAGO_REGISTRADO_FINANZAS'
                        ? 'PAGO REGISTRADO FINANZAS'
                        : 'PENDIENTE PAGO FINANZAS')),
            ])
            ->recordActions([
                Action::make('verResumenOdc')
                    ->label('Ver resumen ODC')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Resumen ODC | ' . (string) ($record->correlativo_odc ?? ('#' . $record->id)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(OdcModalSummaryRenderer::render($record))),

                Action::make('registrarPago')
                    ->label('Subir imagen y marcar pagado')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('success')
                    ->visible(fn ($record): bool => (string) ($record->workflow_post_compra ?? '') === 'PENDIENTE_PAGO_FINANZAS')
                    ->form([
                        FileUpload::make('comprobante_pago_path')
                            ->label('Imagen de comprobante de pago')
                            ->image()
                            ->disk('odc_comprobantes')
                            ->required(),
                    ])
                    ->action(function (array $data, $record): void {
                        $comprobantePath = (string) ($data['comprobante_pago_path'] ?? '');

                        if ($comprobantePath === '') {
                            Notification::make()
                                ->title('Comprobante requerido')
                                ->body('Debes cargar una imagen para marcar la ODC como pagada.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->forceFill([
                            'comprobante_pago_path' => $comprobantePath,
                            'pago_registrado_at' => now(),
                            'pago_por_user_id' => auth()->id(),
                            'estado' => 'PAGADA',
                            'workflow_post_compra' => 'PAGO_REGISTRADO_FINANZAS',
                        ])->save();

                        OrdenCompraComprobante::query()->create([
                            'orden_compra_id' => $record->id,
                            'archivo_path' => $comprobantePath,
                            'subido_por_user_id' => auth()->id(),
                        ]);

                        self::notifyProcuraPaymentRegistered($record);

                        Notification::make()
                            ->title('Pago registrado')
                            ->body('La ODC fue marcada como pagada y enviada a Procura en la ventana PAGOS DE ODC.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function notifyProcuraPaymentRegistered(mixed $record): void
    {
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Procura'))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($record): void {
            $notification = Notification::make()
                ->title('Pago registrado por Finanzas')
                ->body('La ODC ' . (string) $record->correlativo_odc . ' ya tiene comprobante de pago en PAGOS DE ODC.')
                ->success();

            DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
        });
    }
}
