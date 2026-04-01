<?php

namespace App\Filament\Resources\DailyWithdrawals\Pages;

use App\Filament\Resources\DailyWithdrawals\DailyWithdrawalResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;

class ListDailyWithdrawals extends ListRecords
{
    protected static string $resource = DailyWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportControlDespacho')
                ->label('Exportar Control de Despacho')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(route('inventario.retiros-diarios.control-despacho'))
                ->openUrlInNewTab(),

            Action::make('exportControlDespachoRango')
                ->label('Exportar por Rango')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->schema([
                    DatePicker::make('from')
                        ->label('Desde')
                        ->required(),
                    DatePicker::make('to')
                        ->label('Hasta')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $from = (string) ($data['from'] ?? '');
                    $to = (string) ($data['to'] ?? '');

                    $this->redirect(
                        route('inventario.retiros-diarios.control-despacho', [
                            'from' => $from,
                            'to' => $to,
                        ]),
                        navigate: true
                    );
                }),
        ];
    }
}
