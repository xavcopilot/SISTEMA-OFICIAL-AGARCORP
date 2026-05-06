<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Models\Ticket;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        $esGestor = auth()->user()?->can('Manage:Ticket');
        return $table
            ->persistColumnsInSession(true)
            ->headerActions($esGestor ? [
                Action::make('export')
                    ->label('Exportar CSV')
                    ->icon(Heroicon::ArrowDownTray)
                    ->url(url('/tickets/export'))
                    ->openUrlInNewTab()
            ] : [])
            ->columns([
                TextColumn::make('created_at')
                    ->toggleable()
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('departamento')
                    ->toggleable()
                ->label('Departamento'),

                TextColumn::make('tipo_solicitud')
                    ->toggleable()
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => Ticket::TIPO_SOLICITUD_LABELS[$state] ?? (string) $state)
                    ->badge(),

                TextColumn::make('nombre_solicitante')
                    ->toggleable()
                    ->label('Solicitante')
                    ->searchable(),

                // El ESTADO con colores para que sea intuitivo
            $esGestor 
                    ? SelectColumn::make('estado')
                        ->label('Estado')
                        ->options([
                            'Abierto' => 'Abierto',
                            'En Proceso' => 'En Proceso',
                            'Resuelto' => 'Resuelto',
                            'Cancelado' => 'Cancelado',
                        ])
                        ->selectablePlaceholder(false)
                    : TextColumn::make('estado')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'Abierto' => 'danger',
                            'En Proceso' => 'warning',
                            'Resuelto' => 'success',
                            'Cancelado' => 'gray',
                            default => 'gray',
                        }),
            ])
            ->groups($esGestor ? [
                \Filament\Tables\Grouping\Group::make('departamento')
                    ->label('Departamento')
                    ->collapsible(),
            ] : [])
            ->defaultGroup($esGestor ? 'departamento' : null)
            ->defaultSort('created_at', 'desc');
    }
}
