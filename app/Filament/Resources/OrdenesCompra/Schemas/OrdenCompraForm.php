<?php

namespace App\Filament\Resources\OrdenesCompra\Schemas;

use App\Models\Proveedor;
use App\Models\User;
use App\Support\BcvRateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class OrdenCompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ORDEN DE COMPRA')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('correlativo_odc_preview')
                                    ->label('Codigo')
                                    ->content(fn ($record): HtmlString => self::boxedValue((string) ($record?->correlativo_odc ?? '-')))
                                    ->columnSpan(3),

                                Placeholder::make('revision_preview')
                                    ->label('Revision')
                                    ->content(fn (): HtmlString => self::boxedValue('01'))
                                    ->columnSpan(2),

                                Placeholder::make('fecha_formato_preview')
                                    ->label('Fecha')
                                    ->content(fn ($record): HtmlString => self::boxedValue((string) optional($record?->created_at)->format('d/m/Y')))
                                    ->columnSpan(2),

                                Placeholder::make('pagina_preview')
                                    ->label('Pagina')
                                    ->content(fn (): HtmlString => self::boxedValue('01 de 01'))
                                    ->columnSpan(2),

                                Placeholder::make('sumario_correlativo_preview')
                                    ->label('Asociado a sumario de cotizaciones N°')
                                    ->content(fn ($record): HtmlString => self::boxedValue((string) ($record?->sumario?->correlativo_sdc ?? '-')))
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),

                Section::make('Informacion de la empresa')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('empresa_razon_social')
                                    ->label('Nombre o razon social')
                                    ->content(fn (): HtmlString => self::boxedValue('AGARCORP DE VENEZUELA, C.A.'))
                                    ->columnSpan(6),

                                Placeholder::make('empresa_rif')
                                    ->label('RIF')
                                    ->content(fn (): HtmlString => self::boxedValue('J-30693407-3'))
                                    ->columnSpan(3),

                                Placeholder::make('empresa_telefono')
                                    ->label('Telefono')
                                    ->content(fn (): HtmlString => self::boxedValue('0261-7184260'))
                                    ->columnSpan(3),

                                Placeholder::make('empresa_direccion')
                                    ->label('Direccion')
                                    ->content(fn (): HtmlString => self::boxedValue('AV 77 EDIF 5 JULIO PISO 4 OF D/4 SECTOR TIERRA NEGRA MARACAIBO.'))
                                    ->columnSpan(12),
                            ]),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),

                Section::make('Informacion del proveedor')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Toggle::make('es_proveedor_registrado')
                                    ->label('Proveedor registrado en sistema')
                                    ->default(true)
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        $hasProviderId = (int) ($get('proveedor_id') ?? 0) > 0;
                                        $set('es_proveedor_registrado', $hasProviderId);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if ((bool) $state) {
                                            return;
                                        }

                                        $set('proveedor_id', null);
                                    })
                                    ->columnSpanFull(),

                                Select::make('proveedor_id')
                                    ->label('Proveedor')
                                    ->relationship('proveedor', 'nombre')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->live()
                                    ->visible(fn (callable $get): bool => (bool) $get('es_proveedor_registrado'))
                                    ->createOptionForm([
                                        TextInput::make('nombre')->label('Nombre')->required()->maxLength(255),
                                        TextInput::make('rif')->label('RIF')->required()->maxLength(255),
                                        TextInput::make('direccion')->label('Direccion')->maxLength(255),
                                        TextInput::make('ciudad')->label('Ciudad')->maxLength(255),
                                        TextInput::make('email')->label('Email')->email()->maxLength(255),
                                        TextInput::make('contacto')->label('Contacto')->maxLength(255),
                                        TextInput::make('telefono')->label('Telefono')->maxLength(50),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $provider = Proveedor::query()->create($data);
                                        return (int) $provider->id;
                                    })
                                    ->afterStateHydrated(function ($state, callable $set): void {
                                        $set('es_proveedor_registrado', (int) ($state ?? 0) > 0);
                                        self::hydrateProviderFields((int) ($state ?? 0), $set);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $set('es_proveedor_registrado', (int) ($state ?? 0) > 0);
                                        self::hydrateProviderFields((int) ($state ?? 0), $set);
                                    })
                                    ->columnSpan(8),

                                TextInput::make('nombre_proveedor_libre')
                                    ->label('Nombre del proveedor libre')
                                    ->maxLength(255)
                                    ->visible(fn (callable $get): bool => ! (bool) $get('es_proveedor_registrado'))
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if (trim((string) ($state ?? '')) === '') {
                                            return;
                                        }

                                        $set('es_proveedor_registrado', false);
                                        $set('proveedor_id', null);
                                    })
                                    ->dehydrated(false)
                                    ->columnSpan(8),

                                TextInput::make('rif_proveedor')
                                    ->label('RIF')
                                    ->maxLength(255)
                                    ->columnSpan(4),

                                TextInput::make('direccion_proveedor')
                                    ->label('Direccion')
                                    ->maxLength(255)
                                    ->columnSpan(6),

                                TextInput::make('email_proveedor')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('contacto_proveedor')
                                    ->label('Contacto')
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('telefono_proveedor')
                                    ->label('Numero telefono')
                                    ->maxLength(50)
                                    ->disabled(fn (callable $get): bool => (bool) $get('es_proveedor_registrado'))
                                    ->dehydrated(false)
                                    ->columnSpan(2),

                                TextInput::make('ciudad_proveedor')
                                    ->label('Ciudad')
                                    ->maxLength(255)
                                    ->disabled(fn (callable $get): bool => (bool) $get('es_proveedor_registrado'))
                                    ->dehydrated(false)
                                    ->columnSpan(6),

                                Placeholder::make('fecha_entrega_preview')
                                    ->label('Fecha de entrega')
                                    ->content(fn ($record): HtmlString => self::boxedValue((string) optional($record?->created_at)->format('d/m/Y')))
                                    ->columnSpan(6),

                                Actions::make([
                                    Action::make('guardar_proveedor_libre')
                                        ->label('Guardar proveedor en DB')
                                        ->icon('heroicon-o-plus')
                                        ->visible(fn (callable $get): bool => ! (bool) $get('es_proveedor_registrado'))
                                        ->requiresConfirmation()
                                        ->action(function (array $data, callable $set, callable $get): void {
                                            $nombre = (string) ($get('nombre_proveedor_libre') ?? '');
                                            $rif = (string) ($get('rif_proveedor') ?? '');
                                            $direccion = (string) ($get('direccion_proveedor') ?? '');
                                            $email = (string) ($get('email_proveedor') ?? '');
                                            $contacto = (string) ($get('contacto_proveedor') ?? '');
                                            $ciudad = (string) ($get('ciudad_proveedor') ?? '');
                                            $telefono = (string) ($get('telefono_proveedor') ?? '');

                                            if ($nombre === '' || $rif === '') {
                                                Notification::make()
                                                    ->title('Datos incompletos')
                                                    ->body('El nombre y RIF son obligatorios para guardar el proveedor.')
                                                    ->warning()
                                                    ->send();
                                                return;
                                            }

                                            $provider = Proveedor::query()->create([
                                                'nombre' => $nombre,
                                                'rif' => $rif,
                                                'direccion' => $direccion,
                                                'email' => $email,
                                                'contacto' => $contacto,
                                                'ciudad' => $ciudad,
                                                'telefono' => $telefono,
                                            ]);

                                            $set('proveedor_id', $provider->id);
                                            $set('es_proveedor_registrado', true);

                                            self::hydrateProviderFields((int) $provider->id, $set);

                                            Notification::make()
                                                ->title('Proveedor guardado')
                                                ->body('El proveedor "' . $nombre . '" ha sido añadido a la base de datos.')
                                                ->success()
                                                ->send();
                                        }),
                                ])->columnSpanFull(),
                            ]),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),

                Section::make('Detalle de productos')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                $rows = self::normalizeOrderItemRows(is_array($state) ? $state : []);
                                $set('items', $rows);
                                self::recalculateTotals($set, $get);
                            })
                            ->schema([
                                Grid::make(15)
                                    ->schema([
                                        TextInput::make('item')
                                            ->label('Codigo')
                                            ->columnSpan(1),

                                        TextInput::make('descripcion')
                                            ->label('Descripcion')
                                            ->columnSpan(4),

                                        TextInput::make('unidad_medida')
                                            ->label('Unidad MED')
                                            ->columnSpan(1),

                                        TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->live(debounce: 200)
                                            ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                                self::syncOrderItemPricing($set, $get);
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                self::syncOrderItemPricing($set, $get);
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('precio_unitario')
                                            ->label(new HtmlString('<span style="white-space: nowrap;">Valor Unitario $</span>'))
                                            ->numeric()
                                            ->live(debounce: 200)
                                            ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                                self::syncOrderItemPricing($set, $get);
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                self::syncOrderItemPricing($set, $get);
                                            })
                                            ->columnSpan(2),

                                        Placeholder::make('precio_unitario_bs_preview')
                                            ->label(new HtmlString('<span style="white-space: nowrap;">Valor Unitario BS</span>'))
                                            ->content(function (callable $get): string {
                                                $precioUnitario = (float) ($get('precio_unitario') ?? 0);
                                                $tasaBcv = (float) ($get('../../tasa_bcv') ?? 0);

                                                return number_format(self::calculateOrderItemBsUnit($precioUnitario, $tasaBcv), 2, ',', '.');
                                            })
                                            ->columnSpan(3),

                                        Placeholder::make('precio_total_bs_preview')
                                            ->label(new HtmlString('<span style="white-space: nowrap;">Valor Total BS</span>'))
                                            ->content(function (callable $get): string {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $precioUnitario = (float) ($get('precio_unitario') ?? 0);
                                                $tasaBcv = (float) ($get('../../tasa_bcv') ?? 0);

                                                return number_format(self::calculateOrderItemBsTotal($cantidad, $precioUnitario, $tasaBcv), 2, ',', '.');
                                            })
                                            ->columnSpan(3),

                                        Hidden::make('precio_total')
                                            ->dehydrated(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('total_en_letras_preview')
                                    ->label('Valor total en letras')
                                    ->content(fn (callable $get): string => self::totalInWords((float) ($get('total_general') ?? 0)))
                                    ->columnSpan(12),

                                TextInput::make('monto_exento')
                                    ->label('Exento')
                                    ->numeric()
                                    ->default(0)
                                    ->live(debounce: 250)
                                    ->afterStateHydrated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->columnSpan(2),

                                TextInput::make('sub_total')
                                    ->label('Sub total')
                                    ->numeric()
                                    ->dehydrated()
                                    ->columnSpan(2),

                                TextInput::make('iva_16')
                                    ->label('IVA 16%')
                                    ->numeric()
                                    ->dehydrated()
                                    ->columnSpan(2),

                                TextInput::make('gastos_adicionales')
                                    ->label(new HtmlString('<span style="white-space: nowrap;">Gastos adicionales</span>'))
                                    ->numeric()
                                    ->default(0)
                                    ->live(debounce: 250)
                                    ->afterStateHydrated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::recalculateTotals($set, $get))
                                    ->columnSpan(3),

                                TextInput::make('total_general')
                                    ->label('Total')
                                    ->numeric()
                                    ->dehydrated()
                                    ->columnSpan(3),

                                TextInput::make('estado')
                                    ->label('Estado')
                                    ->readOnly()
                                    ->columnSpan(6),

                                TextInput::make('workflow_post_compra')
                                    ->label('Flujo post-compra')
                                    ->readOnly()
                                    ->columnSpan(6),
                            ]),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),

                Section::make('Condiciones y comentarios')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('sitio_entrega')
                                    ->label('Sitio de entrega')
                                    ->maxLength(255)
                                    ->columnSpan(6),

                                TextInput::make('condicion_pago')
                                    ->label('Condicion de pago')
                                    ->maxLength(255)
                                    ->columnSpan(6),

                                Textarea::make('comentarios')
                                    ->label('Comentarios')
                                    ->rows(2)
                                    ->columnSpan(12),
                            ]),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),

                Section::make('Datos de control')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('tasa_bcv')
                                    ->label('TASA BCV')
                                    ->numeric()
                                    ->step('0.0001')
                                    ->live(debounce: 200)
                                    ->disabled(fn ($record): bool => filled($record?->pago_registrado_at))
                                    ->afterStateHydrated(function ($state, callable $set): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $rate = app(BcvRateService::class)->rateForOrderCreation();

                                        if ($rate !== null) {
                                            $set('tasa_bcv', round($rate, 4));
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        $set('items', self::normalizeOrderItemRows(is_array($get('items')) ? $get('items') : []));
                                        self::recalculateTotals($set, $get);
                                    })
                                    ->columnSpan(3),

                                Placeholder::make('solicitado_por_preview')
                                    ->label('SOLICITADO POR')
                                    ->content(fn ($record): HtmlString => self::boxedValue((string) ($record?->departamento_solicitante ?: '-')))
                                    ->columnSpan(4),

                                Placeholder::make('asociado_sumario_preview')
                                    ->label('ASOCIADO A SUMARIO DE COTIZACIONES N°')
                                    ->content(fn ($record): HtmlString => self::boxedValue((string) ($record?->sumario?->correlativo_sdc ?: '-')))
                                    ->columnSpan(5),
                            ]),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),

                Section::make('ELABORADO POR / APROBADO POR')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Placeholder::make('elaborado_por_preview')
                                            ->label('Elaborado por')
                                            ->content(fn ($record): HtmlString => self::boxedValue((string) ($record?->elaboradoPor?->name ?: auth()->user()?->name ?: '-'))),

                                        Placeholder::make('elaborado_cargo_preview')
                                            ->label('Cargo (Elaborado por)')
                                            ->content(fn ($record): HtmlString => self::boxedValue((string) ($record?->elaboradoPor?->cargo?->nombre ?: auth()->user()?->cargo?->nombre ?: '-'))),

                                        Placeholder::make('firma_elaborado_preview')
                                            ->label('Firma')
                                            ->content(fn ($record): HtmlString => self::boxedValue($record?->elaborado_firmado_at
                                                ? 'Registrada el ' . $record->elaborado_firmado_at->format('d/m/Y H:i')
                                                : 'Pendiente')),
                                    ])
                                    ->columnSpan(6),

                                Grid::make(1)
                                    ->schema([
                                        Select::make('aprobado_por_user_id')
                                            ->label('Aprobado por')
                                            ->options(fn (): array => self::gerenciaFinanzasOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->required(),

                                        Placeholder::make('aprobado_cargo_preview')
                                            ->label('Cargo (Aprobado por)')
                                            ->content(function (callable $get, $record): HtmlString {
                                                $selectedUserId = (int) ($get('aprobado_por_user_id') ?: $record?->aprobado_por_user_id ?: 0);
                                                $selectedUser = $selectedUserId > 0
                                                    ? User::query()->with('cargo')->find($selectedUserId)
                                                    : null;

                                                return self::boxedValue((string) ($selectedUser?->cargo?->nombre ?: '-'));
                                            }),

                                        Placeholder::make('firma_aprobador_preview')
                                            ->label('Firma')
                                            ->content(fn ($record): HtmlString => self::boxedValue($record?->aprobado_firmado_at
                                                ? 'Registrada el ' . $record->aprobado_firmado_at->format('d/m/Y H:i')
                                                : 'Pendiente')),
                                    ])
                                    ->columnSpan(6),
                            ]),
                    ])
                    ->extraAttributes(['style' => 'border:1px solid #86efac;'])
                    ->columnSpanFull(),
            ]);
    }

    private static function recalculateTotals(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        $tasaBcv = (float) ($get('tasa_bcv') ?? 0);
        [$subTotal, $iva, $totalItemsBs] = self::calculateBsTotals($items, $tasaBcv);
        $montoExento = round((float) ($get('monto_exento') ?? 0), 2);
        $gastosAdicionales = round((float) ($get('gastos_adicionales') ?? 0), 2);
        $totalGeneral = round($totalItemsBs + $montoExento + $gastosAdicionales, 2);

        $set('sub_total', $subTotal);
        $set('iva_16', $iva);
        $set('total_general', $totalGeneral);
    }

    private static function syncOrderItemPricing(callable $set, callable $get): void
    {
        $cantidad = (float) ($get('cantidad') ?? 0);
        $precioUnitario = (float) ($get('precio_unitario') ?? 0);

        $set('precio_total', self::calculateOrderItemUsdTotal($cantidad, $precioUnitario));

        $items = $get('../../items') ?? [];
        $tasaBcv = (float) ($get('../../tasa_bcv') ?? 0);
        [$subTotal, $iva, $totalItemsBs] = self::calculateBsTotals($items, $tasaBcv);
        $montoExento = round((float) ($get('../../monto_exento') ?? 0), 2);
        $gastosAdicionales = round((float) ($get('../../gastos_adicionales') ?? 0), 2);
        $totalGeneral = round($totalItemsBs + $montoExento + $gastosAdicionales, 2);

        $set('../../sub_total', $subTotal);
        $set('../../iva_16', $iva);
        $set('../../total_general', $totalGeneral);
    }

    private static function normalizeOrderItemRows(array $items): array
    {
        return collect($items)
            ->map(function ($item): array {
                if (! is_array($item)) {
                    return [];
                }

                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precioUnitario = (float) ($item['precio_unitario'] ?? 0);
                $item['precio_total'] = self::calculateOrderItemUsdTotal($cantidad, $precioUnitario);

                return $item;
            })
            ->all();
    }

    private static function calculateOrderItemUsdTotal(float $cantidad, float $precioUnitario): float
    {
        return round($cantidad * $precioUnitario, 2);
    }

    private static function calculateOrderItemBsUnit(float $precioUnitario, float $tasaBcv): float
    {
        return round($precioUnitario * max($tasaBcv, 0), 2);
    }

    private static function calculateOrderItemBsTotal(float $cantidad, float $precioUnitario, float $tasaBcv): float
    {
        return round(self::calculateOrderItemUsdTotal($cantidad, $precioUnitario) * max($tasaBcv, 0), 2);
    }

    private static function calculateBsTotals(array $items, float $tasaBcv): array
    {
        $subtotalBs = round(collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->sum(function (array $item) use ($tasaBcv): float {
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precioUnitario = (float) ($item['precio_unitario'] ?? 0);

                return self::calculateOrderItemBsTotal($cantidad, $precioUnitario, $tasaBcv);
            }), 2);

        $ivaBs = round($subtotalBs * 0.16, 2);
        $totalItemsBs = round($subtotalBs + $ivaBs, 2);

        return [$subtotalBs, $ivaBs, $totalItemsBs];
    }

    private static function hydrateProviderFields(int $providerId, callable $set): void
    {
        if ($providerId <= 0) {
            return;
        }

        $provider = Proveedor::query()->find($providerId);

        if (! $provider) {
            return;
        }

        $set('rif_proveedor', (string) ($provider->rif ?? ''));
        $set('direccion_proveedor', (string) ($provider->direccion ?? ''));
        $set('email_proveedor', (string) ($provider->email ?? ''));
        $set('contacto_proveedor', (string) ($provider->contacto ?? ''));
        $set('ciudad_proveedor', (string) ($provider->ciudad ?? ''));
        $set('telefono_proveedor', (string) ($provider->telefono ?? ''));
    }

    private static function totalInWords(float $amount): string
    {
        $integer = (int) floor($amount);
        $decimal = (int) round(($amount - $integer) * 100);

        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
            $words = strtoupper((string) $formatter->format($integer));

            return trim($words . ' BOLIVARES CON ' . str_pad((string) $decimal, 2, '0', STR_PAD_LEFT) . '/100');
        }

        return 'TOTAL EN LETRAS NO DISPONIBLE';
    }

    private static function boxedValue(string $value): HtmlString
    {
        $safeValue = htmlspecialchars($value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8');

        return new HtmlString(
            '<div style="min-height:40px;display:flex;align-items:center;padding:0 12px;border:1px solid #d1d5db;border-radius:8px;background:#ffffff;color:#111827;">'
            . $safeValue .
            '</div>'
        );
    }

    private static function gerenciaFinanzasOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Gerencia de Finanzas'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }
}

