<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Filament\Resources\Tickets\Schemas\TicketInfolist;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationLabel = 'Tickets de Soporte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nombre_solicitante';

    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }

public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
    
    // Gestores con visibilidad global de tickets
    if (auth()->user()->hasRole(['admin', 'Alta Gerencia', 'A.I.T'])) {
        return $query;
    }

    return $query->where('user_id', auth()->id());
}

public static function getNavigationBadge(): ?string
{
    // El globito de notificación solo para gestores
    if (auth()->user()->hasRole(['admin', 'Alta Gerencia', 'A.I.T'])) {
        return static::getModel()::where('estado', 'Abierto')->count();
    }
    
    return null;
}

public static function canCreate(): bool
{
    if (! auth()->check()) {
        return false;
    }

    // A.I.T gestiona tickets y no debe auto-registrarse solicitudes.
    return ! auth()->user()->hasRole(['A.I.T']);
}


}
