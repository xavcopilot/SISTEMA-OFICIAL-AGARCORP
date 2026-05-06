<?php

namespace App\Filament\Resources\Sumarios\Schemas;

use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\User;
use App\Support\SumarioProviderGrouping;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SumarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('elaborado_por_user_id')
                    ->default(fn () => auth()->id()),

                Placeholder::make('sumario_sdc_style')
                    ->hiddenLabel()
                    ->content(new HtmlString('<style>
                        .sdc-sheet {
                            --sdc-surface: #ffffff;
                            --sdc-surface-soft: #eaf2ff;
                            --sdc-surface-muted: #dbe9ff;
                            --sdc-border: #b7cff3;
                            --sdc-border-strong: #5ea2f7;
                            --sdc-text: #0b1f44;
                            --sdc-accent: #2563eb;
                            --sdc-accent-soft: #60a5fa;
                            --sdc-ring: rgba(37, 99, 235, .18);
                            width: 100%;
                            border: 1px solid var(--sdc-border);
                            border-radius: 16px;
                            background: linear-gradient(180deg, color-mix(in srgb, var(--sdc-surface-soft) 42%, var(--sdc-surface) 58%) 0%, var(--sdc-surface) 100%);
                            overflow: hidden;
                            box-shadow: 0 10px 28px rgba(37, 99, 235, .12);
                        }
                        .dark .sdc-sheet {
                            --sdc-surface: #0b1528;
                            --sdc-surface-soft: #0f1f3a;
                            --sdc-surface-muted: #122746;
                            --sdc-border: #26456f;
                            --sdc-border-strong: #3b82f6;
                            --sdc-text: #e8f2ff;
                            --sdc-accent: #60a5fa;
                            --sdc-accent-soft: #93c5fd;
                            --sdc-ring: rgba(96, 165, 250, .26);
                            box-shadow: 0 12px 30px rgba(3, 10, 26, .42);
                        }
                        .sdc-sheet .fi-section-header {
                            padding: 10px 14px;
                            background: linear-gradient(90deg, color-mix(in srgb, var(--sdc-accent) 16%, var(--sdc-surface-muted) 84%) 0%, var(--sdc-surface-muted) 100%);
                            border-bottom: 1px solid var(--sdc-border);
                        }
                        .sdc-sheet .fi-section-header-heading { font-weight: 800; letter-spacing: .05em; text-transform: uppercase; font-size: 12px; color: var(--sdc-text); }
                        .sdc-header, .sdc-proveedores, .sdc-items, .sdc-cuadro, .sdc-footer { border: 1px solid var(--sdc-border); background: transparent; }
                        .sdc-header .fi-section-content, .sdc-proveedores .fi-section-content, .sdc-items .fi-section-content, .sdc-cuadro .fi-section-content, .sdc-footer .fi-section-content { padding: 12px; background: transparent; }
                        .sdc-cuadro .fi-section-content { overflow-x: auto; padding: 8px; }
                        .sdc-sheet .fi-grid { width: 100%; }
                        .sdc-header .fi-input-wrp, .sdc-proveedores .fi-input-wrp, .sdc-items .fi-input-wrp, .sdc-cuadro .fi-input-wrp, .sdc-footer .fi-input-wrp {
                            border-radius: 12px !important;
                            min-height: 38px;
                            border: 1.5px solid var(--sdc-accent-soft) !important;
                            background: var(--sdc-surface);
                            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--sdc-accent) 18%, transparent);
                        }
                        .sdc-header .fi-input-wrp:focus-within,
                        .sdc-proveedores .fi-input-wrp:focus-within,
                        .sdc-items .fi-input-wrp:focus-within,
                        .sdc-cuadro .fi-input-wrp:focus-within,
                        .sdc-footer .fi-input-wrp:focus-within {
                            border-color: var(--sdc-accent) !important;
                            box-shadow: 0 0 0 3px var(--sdc-ring), inset 0 0 0 1px color-mix(in srgb, var(--sdc-accent) 40%, transparent);
                        }
                        .sdc-label-box {
                            border: 1px solid var(--sdc-border-strong);
                            background: linear-gradient(180deg, color-mix(in srgb, var(--sdc-accent) 14%, var(--sdc-surface-muted) 86%) 0%, var(--sdc-surface-muted) 100%);
                            padding: 8px 10px;
                            font-size: 11px;
                            font-weight: 700;
                            text-align: center;
                            letter-spacing: .03em;
                            border-radius: 12px;
                            color: var(--sdc-text);
                        }
                        .sdc-header .fi-input,
                        .sdc-proveedores .fi-input,
                        .sdc-items .fi-input,
                        .sdc-cuadro .fi-input,
                        .sdc-footer .fi-input,
                        .sdc-header .fi-select-input,
                        .sdc-proveedores .fi-select-input,
                        .sdc-items .fi-select-input,
                        .sdc-cuadro .fi-select-input,
                        .sdc-footer .fi-select-input {
                            color: var(--sdc-text);
                        }
                        .sdc-top-title {
                            border: 1px solid var(--sdc-border-strong);
                            text-align: center;
                            font-weight: 800;
                            font-size: 20px;
                            letter-spacing: .05em;
                            padding: 12px;
                            border-radius: 14px;
                            background: linear-gradient(120deg, color-mix(in srgb, var(--sdc-accent) 16%, var(--sdc-surface-soft) 84%) 0%, color-mix(in srgb, var(--sdc-accent-soft) 14%, var(--sdc-surface-soft) 86%) 100%);
                            color: var(--sdc-text);
                            text-shadow: 0 1px 0 rgba(255, 255, 255, .12);
                        }
                        .sdc-meta-box {
                            border: 1px solid var(--sdc-border-strong);
                            border-left: 4px solid var(--sdc-accent);
                            padding: 10px 12px;
                            font-size: 12px;
                            line-height: 1.4;
                            border-radius: 14px;
                            background: linear-gradient(180deg, color-mix(in srgb, var(--sdc-surface-soft) 86%, var(--sdc-accent-soft) 14%) 0%, var(--sdc-surface-soft) 100%);
                            color: var(--sdc-text);
                        }
                        .sdc-table-wide {
                            width: 100%;
                            min-width: 0;
                        }
                        .sdc-cuadro [data-field-wrapper] { border: 0; padding: 0; background: transparent; }
                        .sdc-cuadro .fi-fo-repeater { gap: 0; margin-top: 0; }
                        .sdc-cuadro .fi-fo-repeater-item {
                            border: 0;
                            border-radius: 0 !important;
                            margin-bottom: 0;
                            background: transparent;
                            box-shadow: none;
                        }
                        .sdc-cuadro .fi-fo-repeater-item-header { display: none; }
                        .sdc-cuadro .fi-fo-repeater-item-content { padding: 0 !important; background: transparent; }
                        .sdc-cuadro .fi-fo-repeater-item .fi-fo-field-wrp-label { display: none; }
                        .sdc-cuadro .fi-input { font-size: 12px; }
                        .sdc-cuadro .fi-select-input { font-size: 12px; min-width: 120px; }
                        .sdc-cuadro .sdc-label-box {
                            border: 1px solid var(--sdc-border-strong);
                            border-radius: 0;
                            background: color-mix(in srgb, var(--sdc-surface-soft) 72%, white 28%);
                            padding: 6px 8px;
                            font-size: 10px;
                            font-weight: 700;
                        }
                        .sdc-cuadro .fi-input-wrp {
                            border: 1px solid var(--sdc-border) !important;
                            border-radius: 0 !important;
                            min-height: 34px;
                            background: #fff;
                            box-shadow: none;
                        }
                        .sdc-cuadro .fi-input-wrp:focus-within {
                            border-color: var(--sdc-accent) !important;
                            box-shadow: 0 0 0 2px var(--sdc-ring);
                        }
                        .sdc-cuadro .sdc-cant-cell .fi-input {
                            min-width: 5ch;
                        }
                        .sdc-cuadro .sdc-pu-cell .fi-input {
                            min-width: 4ch;
                        }
                        .sdc-edge-right-cell { margin-right: 0; }
                        .sdc-edge-right-cell .sdc-label-box { width: 100%; }
                        .sdc-divider-a,
                        .sdc-divider-b,
                        .sdc-divider-c,
                        .sdc-divider-sel {
                            border-left: 2px solid color-mix(in srgb, var(--sdc-accent) 62%, var(--sdc-border) 38%) !important;
                            padding-left: 0 !important;
                        }
                        .sdc-cuadro .sdc-provider-grid {
                            position: relative;
                        }
                        .sdc-cuadro .sdc-provider-grid::before {
                            content: none;
                        }
                        .dark .sdc-divider-a,
                        .dark .sdc-divider-b,
                        .dark .sdc-divider-c,
                        .dark .sdc-divider-sel {
                            border-left-color: color-mix(in srgb, var(--sdc-accent-soft) 60%, var(--sdc-border) 40%);
                        }
                        .sdc-cuadro .fi-fo-field-wrp-label,
                        .sdc-footer .fi-fo-field-wrp-label,
                        .sdc-header .fi-fo-field-wrp-label,
                        .sdc-proveedores .fi-fo-field-wrp-label {
                            font-size: 11px;
                            font-weight: 700;
                            letter-spacing: .04em;
                            text-transform: uppercase;
                        }
                        .sdc-items .fi-fo-checkbox-list-option-label { font-size: 12px; }
                        .sdc-footer .fi-ta {
                            min-height: 42px;
                            resize: vertical;
                        }
                        .sdc-total-caption-offset {
                            margin-top: 24px;
                        }

                        /* Oculta flechas de incremento/decremento en campos numericos del sumario */
                        .sdc-sheet input[type=number]::-webkit-outer-spin-button,
                        .sdc-sheet input[type=number]::-webkit-inner-spin-button {
                            -webkit-appearance: none;
                            margin: 0;
                        }

                        .sdc-sheet input[type=number] {
                            -moz-appearance: textfield;
                            appearance: textfield;
                        }
                    </style>')),

                Section::make('Encabezado')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-header'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('sumario_titulo_visual')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-top-title">SUMARIO DE COTIZACIONES</div>'))
                                    ->columnSpan(8),

                                Placeholder::make('codigo_formato_sdc')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-meta-box"><strong>COD:</strong> ADV-FPR-SDC<br><strong>Revision:</strong> 01<br><strong>Pagina:</strong> 01 de 01</div>'))
                                    ->columnSpan(4),
                            ]),

                        Grid::make(12)
                            ->schema([
                                TextInput::make('correlativo_sdc')
                                    ->label('Sumario N°')
                                    ->placeholder('2026-001')
                                    ->required()
                                    ->unique(table: 'sumarios', column: 'correlativo_sdc', ignoreRecord: true)
                                    ->validationMessages([
                                        'unique' => 'Este Numero de Sumario ya existe. Debes cambiarlo manualmente.',
                                    ])
                                    ->maxLength(50)
                                    ->columnSpan(3),

                                TextInput::make('departamento_solicitante')
                                    ->label('Departamento Solicitante')
                                    ->required()
                                    ->readOnly()
                                    ->columnSpan(5),

                                DatePicker::make('fecha')
                                    ->label('Fecha')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('procedencia')
                                    ->label('Procedencia')
                                    ->options([
                                        'LOCAL' => 'Local',
                                        'IMPORTADO' => 'Importado',
                                    ])
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('solicitud_compra_id')
                                    ->label('Solicitud Compra Base')
                                    ->options(fn (callable $get): array => self::solicitudCompraOptions((int) ($get('solicitud_compra_id') ?? 0)))
                                    ->default(fn (): ?int => request()->integer('solicitud_compra_id') ?: null)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        self::hydrateSolicitudSelection($state, $set, $get);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $solicitud = filled($state) ? SolicitudCompra::find($state) : null;

                                        $set('departamento_solicitante', $solicitud?->departamento_solicitante);
                                        // Al cambiar la solicitud base, los items deben iniciar desmarcados.
                                        $set('selected_item_ids', []);
                                        $set('comparativo_items', []);
                                        self::setColumnTotals([], $set, fn (string $path) => null);
                                    })
                                    ->columnSpan(6),

                                Select::make('tipo_orden')
                                    ->label('Tipo de Orden')
                                    ->options([
                                        'COMPRA' => 'Compra',
                                        'SERVICIO' => 'Servicio',
                                    ])
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('condiciones_pago')
                                    ->label('Condiciones de Pago')
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('tiempo_entrega')
                                    ->label('Tiempo de Entrega')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('PROVEEDORES')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-proveedores'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('label_proveedor_a')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">PROVEEDOR 1</div>'))
                                    ->columnSpan(4),
                                Placeholder::make('label_proveedor_b')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">PROVEEDOR 2</div>'))
                                    ->columnSpan(4),
                                Placeholder::make('label_proveedor_c')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">PROVEEDOR 3</div>'))
                                    ->columnSpan(4),

                                Select::make('proveedor_a_catalogo_id')
                                    ->hiddenLabel()
                                    ->placeholder('Buscar proveedor 1')
                                    ->options(fn (): array => self::providerCatalogOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $set('proveedor_a_catalogo_id', self::resolveProviderIdByName((string) ($get('proveedor_a_nombre') ?? '')));
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        self::syncProviderNameFromCatalog((int) ($state ?? 0), 'proveedor_a_nombre', $set);
                                        self::setColumnTotals(self::recalculateRows($get('comparativo_items') ?? []), $set, $get);
                                    })
                                    ->columnSpan(4),

                                Select::make('proveedor_b_catalogo_id')
                                    ->hiddenLabel()
                                    ->placeholder('Buscar proveedor 2')
                                    ->options(fn (): array => self::providerCatalogOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $set('proveedor_b_catalogo_id', self::resolveProviderIdByName((string) ($get('proveedor_b_nombre') ?? '')));
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        self::syncProviderNameFromCatalog((int) ($state ?? 0), 'proveedor_b_nombre', $set);
                                        self::setColumnTotals(self::recalculateRows($get('comparativo_items') ?? []), $set, $get);
                                    })
                                    ->columnSpan(4),

                                Select::make('proveedor_c_catalogo_id')
                                    ->hiddenLabel()
                                    ->placeholder('Buscar proveedor 3')
                                    ->options(fn (): array => self::providerCatalogOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $set('proveedor_c_catalogo_id', self::resolveProviderIdByName((string) ($get('proveedor_c_nombre') ?? '')));
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        self::syncProviderNameFromCatalog((int) ($state ?? 0), 'proveedor_c_nombre', $set);
                                        self::setColumnTotals(self::recalculateRows($get('comparativo_items') ?? []), $set, $get);
                                    })
                                    ->columnSpan(4),

                                TextInput::make('proveedor_a_nombre')
                                    ->hiddenLabel()
                                    ->placeholder('Nombre proveedor 1')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        self::syncProviderCatalogFromName((string) ($state ?? ''), 'proveedor_a_catalogo_id', $set);
                                        self::setColumnTotals(self::recalculateRows($get('comparativo_items') ?? []), $set, $get);
                                    })
                                    ->columnSpan(4),

                                TextInput::make('proveedor_b_nombre')
                                    ->hiddenLabel()
                                    ->placeholder('Nombre proveedor 2')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        self::syncProviderCatalogFromName((string) ($state ?? ''), 'proveedor_b_catalogo_id', $set);
                                        self::setColumnTotals(self::recalculateRows($get('comparativo_items') ?? []), $set, $get);
                                    })
                                    ->columnSpan(4),

                                TextInput::make('proveedor_c_nombre')
                                    ->hiddenLabel()
                                    ->placeholder('Nombre proveedor 3')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        self::syncProviderCatalogFromName((string) ($state ?? ''), 'proveedor_c_catalogo_id', $set);
                                        self::setColumnTotals(self::recalculateRows($get('comparativo_items') ?? []), $set, $get);
                                    })
                                    ->columnSpan(4),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('ITEMS A INCLUIR EN EL SUMARIO')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-items'])
                    ->schema([
                        CheckboxList::make('selected_item_ids')
                            ->label('Seleccion parcial de items de la solicitud')
                            ->options(function (callable $get): array {
                                $selectedFromState = collect($get('selected_item_ids') ?? [])
                                    ->map(fn ($id): int => (int) $id)
                                    ->filter(fn (int $id): bool => $id > 0)
                                    ->values()
                                    ->all();

                                $selectedFromRows = collect($get('comparativo_items') ?? [])
                                    ->filter(fn ($row): bool => is_array($row) && filled($row['solicitud_compra_item_id'] ?? null))
                                    ->map(fn ($row): int => (int) ($row['solicitud_compra_item_id'] ?? 0))
                                    ->filter(fn (int $id): bool => $id > 0)
                                    ->values()
                                    ->all();

                                $selectedIds = collect(array_merge($selectedFromState, $selectedFromRows))
                                    ->unique()
                                    ->values()
                                    ->all();

                                return self::solicitudItemOptions(
                                    (int) ($get('solicitud_compra_id') ?? 0),
                                    $selectedIds
                                );
                            })
                            ->columns(1)
                            ->searchable()
                            ->bulkToggleable()
                            ->live()
                            ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                if (is_array($state) && $state !== []) {
                                    return;
                                }

                                $rows = $get('comparativo_items') ?? [];
                                $selected = collect($rows)
                                    ->filter(fn ($row) => is_array($row) && filled($row['solicitud_compra_item_id'] ?? null))
                                    ->pluck('solicitud_compra_item_id')
                                    ->map(fn ($id) => (string) $id)
                                    ->values()
                                    ->all();

                                if ($selected !== []) {
                                    $set('selected_item_ids', $selected);
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $selectedIds = collect($state ?? [])
                                    ->map(fn ($id) => (int) $id)
                                    ->filter(fn ($id) => $id > 0)
                                    ->values()
                                    ->all();

                                $existingRows = is_array($get('comparativo_items')) ? $get('comparativo_items') : [];
                                $solicitudId = (int) ($get('solicitud_compra_id') ?? 0);

                                self::syncRowsFromSelectedItems($selectedIds, $existingRows, $solicitudId, $set);
                            }),
                    ])
                    ->columnSpanFull(),

                Section::make('CUADRO COMPARATIVO DE COTIZACIONES')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-cuadro'])
                    ->description('Bloque principal del formato: item, descripcion, UND, cantidad y 3 proveedores con precios.')
                    ->schema([
                        Grid::make(21)
                            ->extraAttributes(['class' => 'sdc-table-wide sdc-provider-grid'])
                            ->schema([
                                Placeholder::make('head_blank_a')
                                    ->hiddenLabel()
                                    ->content('')
                                    ->columnSpan(7),
                                Placeholder::make('head_proveedor_a')
                                    ->hiddenLabel()
                                    ->content(fn (callable $get): HtmlString => new HtmlString('<div class="sdc-label-box">' . e((string) ($get('proveedor_a_nombre') ?: 'PROVEEDOR 1')) . '</div>'))
                                    ->extraAttributes(['class' => 'sdc-divider-a'])
                                    ->columnSpan(4),
                                Placeholder::make('head_proveedor_b')
                                    ->hiddenLabel()
                                    ->content(fn (callable $get): HtmlString => new HtmlString('<div class="sdc-label-box">' . e((string) ($get('proveedor_b_nombre') ?: 'PROVEEDOR 2')) . '</div>'))
                                    ->extraAttributes(['class' => 'sdc-divider-b'])
                                    ->columnSpan(4),
                                Placeholder::make('head_proveedor_c')
                                    ->hiddenLabel()
                                    ->content(fn (callable $get): HtmlString => new HtmlString('<div class="sdc-label-box">' . e((string) ($get('proveedor_c_nombre') ?: 'PROVEEDOR 3')) . '</div>'))
                                    ->extraAttributes(['class' => 'sdc-divider-c'])
                                    ->columnSpan(4),
                                Placeholder::make('head_blank_sel_top')
                                    ->hiddenLabel()
                                    ->content('')
                                    ->columnSpan(2),
                            ]),

                        Grid::make(21)
                            ->extraAttributes(['class' => 'sdc-table-wide sdc-provider-grid'])
                            ->schema([
                                Placeholder::make('head_item')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">ITEM</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_descripcion')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">DESCRIPCION</div>'))
                                    ->columnSpan(4),
                                Placeholder::make('head_und')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">UND</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_cant')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">CANT</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_marca_a')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">MARCA</div>'))
                                    ->extraAttributes(['class' => 'sdc-divider-a'])
                                    ->columnSpan(2),
                                Placeholder::make('head_unit_a')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">P/U</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_total_a')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">P/T</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_marca_b')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">MARCA</div>'))
                                    ->extraAttributes(['class' => 'sdc-divider-b'])
                                    ->columnSpan(2),
                                Placeholder::make('head_unit_b')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">P/U</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_total_b')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">P/T</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_marca_c')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">MARCA</div>'))
                                    ->extraAttributes(['class' => 'sdc-divider-c'])
                                    ->columnSpan(2),
                                Placeholder::make('head_unit_c')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">P/U</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_total_c')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">P/T</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_seleccion')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">SELECCION</div>'))
                                    ->extraAttributes(['class' => 'sdc-edge-right-cell sdc-divider-sel'])
                                    ->columnSpan(2),
                            ]),

                        Repeater::make('comparativo_items')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default([])
                            ->live()
                            ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                $rows = self::recalculateRows($state ?? []);
                                $set('comparativo_items', $rows);
                                self::setColumnTotals($rows, $set, $get);
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $rows = self::recalculateRows($state ?? []);
                                $set('comparativo_items', $rows);
                                self::setColumnTotals($rows, $set, $get);
                            })
                            ->schema([
                                Grid::make(21)
                                    ->extraAttributes(['class' => 'sdc-table-wide sdc-provider-grid'])
                                    ->schema([
                                        TextInput::make('item')
                                            ->label('ITEM')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('descripcion')
                                            ->label('DESCRIPCION')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(4),

                                        TextInput::make('unidad_medida')
                                            ->label('UND')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('cantidad')
                                            ->label('CANT')
                                            ->required()
                                            ->dehydrated()
                                            ->numeric()
                                            ->extraAttributes(['class' => 'sdc-cant-cell'])
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($state ?? 0);

                                                foreach ([1, 2, 3] as $providerNumber) {
                                                    $precioRaw = $get('precio_unitario_prov' . $providerNumber);
                                                    $hasPrecio = filled($precioRaw);
                                                    $precioUnitario = (float) ($precioRaw ?? 0);

                                                    $set(
                                                        'precio_total_prov' . $providerNumber,
                                                        $hasPrecio ? round($cantidad * $precioUnitario, 2) : null
                                                    );
                                                }
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov1')
                                            ->label('MARCA')
                                            ->maxLength(255)
                                            ->extraAttributes(fn (callable $get): array => array_merge(
                                                ['class' => 'sdc-divider-a'],
                                                self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'A')
                                            ))
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov1')
                                            ->label('P/U')
                                            ->numeric()
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $hasPrecio = filled($state);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov1', $hasPrecio ? round($cantidad * $precioUnitario, 2) : null);
                                            })
                                            ->extraAttributes(fn (callable $get): array => array_merge(
                                                ['class' => 'sdc-pu-cell'],
                                                self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'A')
                                            ))
                                            ->columnSpan(1),

                                        TextInput::make('precio_total_prov1')
                                            ->label('P/T')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->extraAttributes(fn (callable $get): array => self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'A'))
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov2')
                                            ->label('MARCA')
                                            ->maxLength(255)
                                            ->extraAttributes(fn (callable $get): array => array_merge(
                                                ['class' => 'sdc-divider-b'],
                                                self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'B')
                                            ))
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov2')
                                            ->label('P/U')
                                            ->numeric()
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $hasPrecio = filled($state);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov2', $hasPrecio ? round($cantidad * $precioUnitario, 2) : null);
                                            })
                                            ->extraAttributes(fn (callable $get): array => array_merge(
                                                ['class' => 'sdc-pu-cell'],
                                                self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'B')
                                            ))
                                            ->columnSpan(1),

                                        TextInput::make('precio_total_prov2')
                                            ->label('P/T')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->extraAttributes(fn (callable $get): array => self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'B'))
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov3')
                                            ->label('MARCA')
                                            ->maxLength(255)
                                            ->extraAttributes(fn (callable $get): array => array_merge(
                                                ['class' => 'sdc-divider-c'],
                                                self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'C')
                                            ))
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov3')
                                            ->label('P/U')
                                            ->numeric()
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $hasPrecio = filled($state);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov3', $hasPrecio ? round($cantidad * $precioUnitario, 2) : null);
                                            })
                                            ->extraAttributes(fn (callable $get): array => array_merge(
                                                ['class' => 'sdc-pu-cell'],
                                                self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'C')
                                            ))
                                            ->columnSpan(1),

                                        TextInput::make('precio_total_prov3')
                                            ->label('P/T')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->extraAttributes(fn (callable $get): array => self::providerCellAttributes((string) ($get('proveedor_seleccionado') ?? ''), 'C'))
                                            ->columnSpan(1),

                                        Select::make('proveedor_seleccionado')
                                            ->label('SELECCION')
                                            ->options(fn (callable $get): array => SumarioProviderGrouping::selectionOptions([
                                                1 => (string) ($get('../../proveedor_a_nombre') ?? ''),
                                                2 => (string) ($get('../../proveedor_b_nombre') ?? ''),
                                                3 => (string) ($get('../../proveedor_c_nombre') ?? ''),
                                            ]))
                                            ->default('')
                                            ->extraAttributes(['class' => 'sdc-divider-sel'])
                                            ->columnSpan(2),

                                        Hidden::make('solicitud_compra_item_id'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('PIE DEL FORMATO')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-footer'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('total_compra_caption')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box sdc-total-caption-offset">TOTAL COMPRA</div>'))
                                    ->columnSpan(3),

                                TextInput::make('total_compra_prov1')
                                    ->label(fn (callable $get): string => SumarioProviderGrouping::totalLabel([
                                        1 => (string) ($get('proveedor_a_nombre') ?? ''),
                                        2 => (string) ($get('proveedor_b_nombre') ?? ''),
                                        3 => (string) ($get('proveedor_c_nombre') ?? ''),
                                    ], 1))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->visible(fn (callable $get): bool => SumarioProviderGrouping::totalVisible([
                                        1 => (string) ($get('proveedor_a_nombre') ?? ''),
                                        2 => (string) ($get('proveedor_b_nombre') ?? ''),
                                        3 => (string) ($get('proveedor_c_nombre') ?? ''),
                                    ], 1))
                                    ->columnSpan(3),

                                TextInput::make('total_compra_prov2')
                                    ->label(fn (callable $get): string => SumarioProviderGrouping::totalLabel([
                                        1 => (string) ($get('proveedor_a_nombre') ?? ''),
                                        2 => (string) ($get('proveedor_b_nombre') ?? ''),
                                        3 => (string) ($get('proveedor_c_nombre') ?? ''),
                                    ], 2))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->visible(fn (callable $get): bool => SumarioProviderGrouping::totalVisible([
                                        1 => (string) ($get('proveedor_a_nombre') ?? ''),
                                        2 => (string) ($get('proveedor_b_nombre') ?? ''),
                                        3 => (string) ($get('proveedor_c_nombre') ?? ''),
                                    ], 2))
                                    ->columnSpan(3),

                                TextInput::make('total_compra_prov3')
                                    ->label(fn (callable $get): string => SumarioProviderGrouping::totalLabel([
                                        1 => (string) ($get('proveedor_a_nombre') ?? ''),
                                        2 => (string) ($get('proveedor_b_nombre') ?? ''),
                                        3 => (string) ($get('proveedor_c_nombre') ?? ''),
                                    ], 3))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->visible(fn (callable $get): bool => SumarioProviderGrouping::totalVisible([
                                        1 => (string) ($get('proveedor_a_nombre') ?? ''),
                                        2 => (string) ($get('proveedor_b_nombre') ?? ''),
                                        3 => (string) ($get('proveedor_c_nombre') ?? ''),
                                    ], 3))
                                    ->columnSpan(3),

                                Select::make('prioridad')
                                    ->label('Prioridad')
                                    ->options([
                                        'MEJOR_PRECIO' => 'MEJOR PRECIO',
                                        'CALIDAD' => 'MEJOR SERVICIO/CALIDAD',
                                    ])
                                    ->required()
                                    ->columnSpan(4),

                                Textarea::make('observaciones')
                                    ->label('OBSERVACIONES')
                                    ->rows(1)
                                    ->columnSpan(8),

                                Grid::make(2)
                                    ->schema([
                                        Section::make('Elaborado por')
                                            ->schema([
                                                TextInput::make('elaborado_por_preview')
                                                    ->label('Elaborado por')
                                                    ->default(fn (): string => (string) (auth()->user()?->name ?? 'N/A'))
                                                    ->readOnly()
                                                    ->dehydrated(false),
                                                TextInput::make('elaborado_cargo_preview')
                                                    ->label('Cargo')
                                                    ->default(fn (): string => (string) (auth()->user()?->cargo?->nombre ?? 'Sin cargo'))
                                                    ->readOnly()
                                                    ->dehydrated(false),
                                                TextInput::make('firma_procura_preview')
                                                    ->label('Firma procura')
                                                    ->default('Se registra al enviar')
                                                    ->readOnly()
                                                    ->dehydrated(false),
                                                TextInput::make('fecha_elaborado_preview')
                                                    ->label('Fecha')
                                                    ->default(fn (): string => now()->format('d/m/Y'))
                                                    ->readOnly()
                                                    ->dehydrated(false),
                                            ]),

                                        Section::make('Revisado por')
                                            ->schema([
                                                Select::make('revisado_por_user_id')
                                                    ->label('Aprobado por')
                                                    ->options(fn (): array => self::financeReviewerOptions())
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateHydrated(function ($state, callable $set): void {
                                                        $set('revisado_cargo_preview', self::userCargoById($state));
                                                    })
                                                    ->afterStateUpdated(function ($state, callable $set): void {
                                                        $set('revisado_cargo_preview', self::userCargoById($state));
                                                    }),
                                                TextInput::make('revisado_cargo_preview')
                                                    ->label('Cargo')
                                                    ->readOnly()
                                                    ->dehydrated(false),
                                                TextInput::make('firma_revisado_preview')
                                                    ->label('Firma')
                                                    ->default('Se registra al validar en Finanzas')
                                                    ->readOnly()
                                                    ->dehydrated(false),
                                                TextInput::make('fecha_revisado_preview')
                                                    ->label('Fecha')
                                                    ->default('-')
                                                    ->readOnly()
                                                    ->dehydrated(false),
                                            ]),
                                    ])
                                    ->columnSpanFull(),

                                Hidden::make('proveedor_ganador_id'),
                                Hidden::make('estado'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function solicitudCompraOptions(?int $currentSolicitudId = null): array
    {
        $options = SolicitudCompra::query()
            ->where('estado', SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA)
            ->whereNotNull('fecha_receptor')
            ->where('estado', '!=', 'RECHAZADA')
            ->whereHas('items', function ($query): void {
                $query->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)');
            })
            ->orderByDesc('fecha_receptor')
            ->orderByDesc('id')
            ->get(['id', 'codigo_control', 'numero_solicitud_usuario', 'departamento_solicitante', 'para_ser_usado_en'])
            ->mapWithKeys(function (SolicitudCompra $solicitud): array {
                $codigo = $solicitud->codigo_control ?: (string) $solicitud->id;
                $numeroSolicitud = $solicitud->numero_solicitud_usuario ?: $solicitud->id;
                $uso = trim((string) ($solicitud->para_ser_usado_en ?? ''));
                $usoCorto = $uso !== '' ? mb_strimwidth($uso, 0, 60, '...') : 'Sin detalle de uso';

                $label = 'N° ' . $numeroSolicitud
                    . ' | ' . $codigo
                    . ' | ' . ($solicitud->departamento_solicitante ?: 'Sin departamento')
                    . ' | ' . $usoCorto;

                return [$solicitud->id => $label];
            })
            ->all();

        $currentSolicitudId = (int) ($currentSolicitudId ?? 0);

        if ($currentSolicitudId > 0 && ! array_key_exists($currentSolicitudId, $options)) {
            $currentSolicitud = SolicitudCompra::query()
                ->whereKey($currentSolicitudId)
                ->first(['id', 'codigo_control', 'numero_solicitud_usuario', 'departamento_solicitante', 'para_ser_usado_en']);

            if ($currentSolicitud) {
                $codigo = $currentSolicitud->codigo_control ?: (string) $currentSolicitud->id;
                $numeroSolicitud = $currentSolicitud->numero_solicitud_usuario ?: $currentSolicitud->id;
                $uso = trim((string) ($currentSolicitud->para_ser_usado_en ?? ''));
                $usoCorto = $uso !== '' ? mb_strimwidth($uso, 0, 60, '...') : 'Sin detalle de uso';

                $options[$currentSolicitud->id] = 'N° ' . $numeroSolicitud
                    . ' | ' . $codigo
                    . ' | ' . ($currentSolicitud->departamento_solicitante ?: 'Sin departamento')
                    . ' | ' . $usoCorto
                    . ' | (actual del sumario)';
            }
        }

        return $options;
    }

    public static function financeReviewerOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Validador Finanzas'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }

    private static function userCargoById(mixed $userId): string
    {
        if (! filled($userId)) {
            return 'Sin cargo';
        }

        return (string) (User::query()->whereKey((int) $userId)->with('cargo')->first()?->cargo?->nombre ?? 'Sin cargo');
    }

    private static function hydrateSolicitudSelection(mixed $state, callable $set, callable $get): void
    {
        $solicitudId = (int) ($state ?? 0);

        if ($solicitudId <= 0) {
            return;
        }

        $set('departamento_solicitante', SolicitudCompra::query()->whereKey($solicitudId)->value('departamento_solicitante'));

        $existingSelected = collect($get('selected_item_ids') ?? [])
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $existingRows = is_array($get('comparativo_items')) ? $get('comparativo_items') : [];

        if ($existingRows !== []) {
            if ($existingSelected === []) {
                $existingSelected = collect($existingRows)
                    ->pluck('solicitud_compra_item_id')
                    ->filter(fn ($id): bool => filled($id))
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->values()
                    ->all();

                if ($existingSelected !== []) {
                    $set('selected_item_ids', array_map(fn (int $id): string => (string) $id, $existingSelected));
                }
            }

            self::setColumnTotals(self::recalculateRows($existingRows), $set, $get);

            return;
        }

        $selectedIds = $existingSelected;

        if ($selectedIds === []) {
            $set('selected_item_ids', []);
            $set('comparativo_items', []);
            self::setColumnTotals([], $set, $get);

            return;
        }

        if ($selectedIds !== []) {
            self::syncRowsFromSelectedItems($selectedIds, [], $solicitudId, $set);
        }
    }

    public static function solicitudItemOptions(int $solicitudId, array $forceIncludeItemIds = []): array
    {
        if ($solicitudId <= 0) {
            return [];
        }

        $forceIncludeItemIds = collect($forceIncludeItemIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->where(function ($query) use ($forceIncludeItemIds): void {
                $query->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)');

                if ($forceIncludeItemIds !== []) {
                    $query->orWhereIn('id', $forceIncludeItemIds);
                }
            })
            ->orderBy('item')
            ->get()
            ->mapWithKeys(function (SolicitudCompraItem $item): array {
                $cantidadPendiente = max(
                    round((float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2)
                    - round((float) ($item->cantidad_en_sumario ?? 0), 2),
                    0
                );

                $label = sprintf(
                    '#%s | %s | %s | Disponible para cotizar: %s',
                    (string) ($item->item ?: $item->id),
                    (string) $item->descripcion,
                    (string) $item->unidad_medida,
                    number_format($cantidadPendiente, 2, ',', '.')
                );

                return [(string) $item->id => $label];
            })
            ->all();
    }

    public static function syncRowsFromSelectedItems(array $selectedIds, array $existingRows, int $solicitudId, callable $set): void
    {
        if ($solicitudId <= 0 || $selectedIds === []) {
            $set('comparativo_items', []);
            self::setColumnTotals([], $set, fn (string $path) => null);

            return;
        }

        $items = SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->whereIn('id', $selectedIds)
            ->orderBy('item')
            ->get()
            ->keyBy('id');

        $existingByItemId = collect($existingRows)
            ->filter(fn ($row): bool => is_array($row) && filled($row['solicitud_compra_item_id'] ?? null))
            ->keyBy(fn ($row) => (int) $row['solicitud_compra_item_id']);

        $rows = [];

        foreach ($selectedIds as $selectedId) {
            $selectedId = (int) $selectedId;
            $item = $items->get($selectedId);

            if (! $item) {
                continue;
            }

            $existing = $existingByItemId->get($selectedId, []);
            $cantidadPendiente = max(
                round((float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2)
                - round((float) ($item->cantidad_en_sumario ?? 0), 2),
                0
            );

            $cantidad = (float) ($existing['cantidad'] ?? $cantidadPendiente);

            $rows[] = [
                'solicitud_compra_item_id' => $selectedId,
                'item' => $item->item ?: $item->id,
                'descripcion' => $item->descripcion,
                'unidad_medida' => $item->unidad_medida,
                'cantidad' => $cantidad,
                'marca_prov1' => $existing['marca_prov1'] ?? null,
                'precio_unitario_prov1' => filled($existing['precio_unitario_prov1'] ?? null) ? (float) $existing['precio_unitario_prov1'] : null,
                'precio_total_prov1' => filled($existing['precio_total_prov1'] ?? null) ? (float) $existing['precio_total_prov1'] : null,
                'marca_prov2' => $existing['marca_prov2'] ?? null,
                'precio_unitario_prov2' => filled($existing['precio_unitario_prov2'] ?? null) ? (float) $existing['precio_unitario_prov2'] : null,
                'precio_total_prov2' => filled($existing['precio_total_prov2'] ?? null) ? (float) $existing['precio_total_prov2'] : null,
                'marca_prov3' => $existing['marca_prov3'] ?? null,
                'precio_unitario_prov3' => filled($existing['precio_unitario_prov3'] ?? null) ? (float) $existing['precio_unitario_prov3'] : null,
                'precio_total_prov3' => filled($existing['precio_total_prov3'] ?? null) ? (float) $existing['precio_total_prov3'] : null,
                'proveedor_seleccionado' => $existing['proveedor_seleccionado'] ?? '',
            ];
        }

        $rows = self::recalculateRows($rows);

        $set('comparativo_items', $rows);
        self::setColumnTotals($rows, $set, fn (string $path) => null);
    }

    public static function recalculateRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                $cantidad = (float) ($row['cantidad'] ?? 0);

                $hasProv1 = filled($row['precio_unitario_prov1'] ?? null);
                $hasProv2 = filled($row['precio_unitario_prov2'] ?? null);
                $hasProv3 = filled($row['precio_unitario_prov3'] ?? null);

                $precioUnitario1 = (float) ($row['precio_unitario_prov1'] ?? 0);
                $precioUnitario2 = (float) ($row['precio_unitario_prov2'] ?? 0);
                $precioUnitario3 = (float) ($row['precio_unitario_prov3'] ?? 0);

                $row['precio_total_prov1'] = $hasProv1 ? round($cantidad * $precioUnitario1, 2) : null;
                $row['precio_total_prov2'] = $hasProv2 ? round($cantidad * $precioUnitario2, 2) : null;
                $row['precio_total_prov3'] = $hasProv3 ? round($cantidad * $precioUnitario3, 2) : null;

                return $row;
            })
            ->values()
            ->all();
    }

    public static function setColumnTotals(array $rows, callable $set, ?callable $get = null): void
    {
        $providerNames = [
            1 => (string) ($get ? $get('proveedor_a_nombre') : ''),
            2 => (string) ($get ? $get('proveedor_b_nombre') : ''),
            3 => (string) ($get ? $get('proveedor_c_nombre') : ''),
        ];

        $totals = SumarioProviderGrouping::groupedTotalsFromRows($providerNames, $rows);

        $set('total_compra_prov1', $totals[1]);
        $set('total_compra_prov2', $totals[2]);
        $set('total_compra_prov3', $totals[3]);
    }

    /**
     * @return array<int, string>
     */
    private static function providerCatalogOptions(): array
    {
        return Proveedor::query()
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->map(fn ($name): string => trim((string) $name))
            ->all();
    }

    private static function resolveProviderIdByName(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return Proveedor::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($name)])
            ->value('id');
    }

    private static function syncProviderNameFromCatalog(int $providerId, string $targetNameField, callable $set): void
    {
        if ($providerId <= 0) {
            return;
        }

        $name = (string) (Proveedor::query()->whereKey($providerId)->value('nombre') ?? '');

        if (trim($name) !== '') {
            $set($targetNameField, $name);
        }
    }

    private static function syncProviderCatalogFromName(string $name, string $targetCatalogField, callable $set): void
    {
        $set($targetCatalogField, self::resolveProviderIdByName($name));
    }

    /**
     * @return array<string, string>
     */
    private static function providerCellAttributes(string $selectedProvider, string $providerKey): array
    {
        $selected = strtoupper(trim($selectedProvider));

        if ($selected === '1') {
            $selected = 'A';
        } elseif ($selected === '2') {
            $selected = 'B';
        } elseif ($selected === '3') {
            $selected = 'C';
        }

        if ($selected !== $providerKey) {
            return [];
        }

        return [
            'style' => 'background:#dcfce7;border-color:#86efac;',
        ];
    }
}

