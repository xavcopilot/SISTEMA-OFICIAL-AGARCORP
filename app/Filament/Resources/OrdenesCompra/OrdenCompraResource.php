<?php

namespace App\Filament\Resources\OrdenesCompra;

use App\Filament\Resources\OrdenesCompra\Pages;
use App\Filament\Resources\OrdenesCompra\Schemas\OrdenCompraForm;
use App\Filament\Resources\OrdenesCompra\Tables\OrdenesCompraTable;
use App\Models\OrdenCompra;
use App\Models\Sumario;
use BackedEnum;
use App\Support\SumarioFinanceApprovalService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrdenCompraResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Ordenes de Compra';

    protected static ?string $modelLabel = 'Orden de Compra';

    protected static ?string $pluralModelLabel = 'Ordenes de Compra';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return OrdenCompraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdenesCompraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdenesCompra::route('/'),
            'edit' => Pages\EditOrdenCompra::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Procura');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (self::hasOperationalReadAccess()) {
            return true;
        }

        $solicitanteId = (int) ($record->sumario?->solicitudCompra?->solicitado_por_user_id ?? 0);

        return $solicitanteId > 0 && (int) $user->id === $solicitanteId;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::hasReadAccess()) {
            return null;
        }

        $count = static::countCreationNotifications()
            + static::countCorrectionNotifications()
            + static::countPaymentNotifications();

        return $count > 0 ? (string) $count : null;
    }

    public static function countCreationNotifications(): int
    {
        if (! self::hasReadAccess()) {
            return 0;
        }

        $sumarios = Sumario::query()
            ->with(['ordenesCompra', 'items.opciones', 'items.solicitudCompraItem.solicitudCompra'])
            ->where('workflow_estado', 'APROBADO_GERENCIA_FINANZAS')
            ->orderByDesc('id')
            ->get();

        if ($sumarios->isEmpty()) {
            return 0;
        }

        $service = app(SumarioFinanceApprovalService::class);

        return (int) $sumarios
            ->filter(function (Sumario $sumario) use ($service): bool {
                $groups = $service->pendingProviderGroups($sumario)
                    ->filter(function (array $group) use ($sumario): bool {
                        $query = $sumario->ordenesCompra()->where('departamento_solicitante', (string) $group['departamento_solicitante']);

                        if (filled($group['provider_id'])) {
                            $query->where('proveedor_id', (int) $group['provider_id']);
                        }

                        $query->where(function ($workflowQuery): void {
                            $workflowQuery
                                ->whereNull('workflow_post_compra')
                                ->orWhere('workflow_post_compra', '!=', 'BORRADOR_ODC');
                        });

                        return ! $query->exists();
                    })
                    ->values();

                return $groups->isNotEmpty();
            })
            ->count();
    }

    public static function countCorrectionNotifications(): int
    {
        if (! self::hasReadAccess()) {
            return 0;
        }

        return (int) static::getEloquentQuery()
            ->where('estado', 'RECHAZADA')
            ->whereIn('rechazo_etapa', ['gerencia_finanzas', 'validacion_finanzas'])
            ->count();
    }

    public static function countPaymentNotifications(): int
    {
        if (! self::hasReadAccess()) {
            return 0;
        }

        return (int) static::getEloquentQuery()
            ->where('workflow_post_compra', 'PAGO_REGISTRADO_FINANZAS')
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() !== null ? 'warning' : 'gray';
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (self::hasEditAccess()) {
            if ((string) ($record->estado ?? '') === 'RECHAZADA'
                && (string) ($record->rechazo_etapa ?? '') === 'historial') {
                return false;
            }

            return true;
        }

        if ((string) ($record->estado ?? '') === 'RECHAZADA'
            && (string) ($record->rechazo_etapa ?? '') === 'historial') {
            return false;
        }

        if ($user->hasRole('Procura')
            && in_array((string) ($record->workflow_post_compra ?? ''), [
                'PAGO_REGISTRADO_FINANZAS',
                'PAGADO_Y_EN_TRANSITO',
            ], true)) {
            return true;
        }

        return $user->can('GenerateOdcs:Sumario')
            && in_array((string) ($record->workflow_post_compra ?? ''), [
                'BORRADOR_ODC',
                'PENDIENTE_APROBACION_GERENCIA_FINANZAS',
            ], true);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['sumario.solicitudCompra', 'proveedor']);

        if (self::hasOperationalReadAccess()) {
            return $query;
        }

        $userId = (int) (auth()->id() ?? 0);

        return $query->whereHas('sumario.solicitudCompra', fn (Builder $subQuery): Builder => $subQuery
            ->where('solicitado_por_user_id', $userId));
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check() && auth()->user()?->can('Delete:OrdenCompra');
    }

    private static function hasReadAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (self::hasOperationalReadAccess()) {
            return true;
        }

        return OrdenCompra::query()
            ->whereHas('sumario.solicitudCompra', fn (Builder $query): Builder => $query
                ->where('solicitado_por_user_id', $user->id))
            ->exists();
    }

    private static function hasOperationalReadAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('ViewAny:OrdenCompra');
    }

    private static function hasEditAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('Update:OrdenCompra');
    }
}
