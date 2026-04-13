<?php

namespace App\Filament\Resources\Sumarios\Schemas;

use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
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
                        .sdc-cuadro .fi-section-content { overflow-x: auto; }
                        .sdc-sheet .fi-grid { width: 100%; }
                        .sdc-header .fi-input-wrp, .sdc-proveedores .fi-input-wrp, .sdc-items .fi-input-wrp, .sdc-cuadro .fi-input-wrp, .sdc-footer .fi-input-wrp {
                            border-radius: 12px !important;
                            min-height: 38px;
                            border: 1.5px solid var(--sdc-accent-soft) !important;
                            background: color-mix(in srgb, var(--sdc-surface) 78%, var(--sdc-accent-soft) 22%);
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
                        .sdc-table-wide { width: max(1900px, 100%); }
                        .sdc-cuadro [data-field-wrapper] { border: 0; padding: 4px; background: transparent; }
                        .sdc-cuadro .fi-fo-repeater { gap: 10px; }
                        .sdc-cuadro .fi-fo-repeater-item {
                            border: 1px solid var(--sdc-border);
                            border-radius: 16px !important;
                            margin-bottom: 0;
                            background: var(--sdc-surface-soft);
                            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .03), 0 4px 14px rgba(37, 99, 235, .08);
                        }
                        .sdc-cuadro .fi-fo-repeater-item-header { display: none; }
                        .sdc-cuadro .fi-fo-repeater-item-content { padding: 10px !important; background: transparent; }
                        .sdc-cuadro .fi-input { font-size: 13px; }
                        .sdc-cuadro .fi-select-input { font-size: 13px; min-width: 120px; }
                        .sdc-edge-right-cell { margin-right: -6px; }
                        .sdc-edge-right-cell .sdc-label-box { width: calc(100% + 6px); }
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
                        .sdc-footer .fi-ta { min-height: 86px; }
                    </style>')),

                Section::make('SUMARIO DE COTIZACIONES')
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
                                    ->options(fn (): array => self::solicitudCompraOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $solicitud = filled($state) ? SolicitudCompra::find($state) : null;

                                        $set('departamento_solicitante', $solicitud?->departamento_solicitante);
                                        $set('selected_item_ids', []);
                                        $set('comparativo_items', []);
                                        self::setColumnTotals([], $set);
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

                                TextInput::make('proveedor_a_nombre')
                                    ->hiddenLabel()
                                    ->placeholder('Nombre proveedor 1')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                TextInput::make('proveedor_b_nombre')
                                    ->hiddenLabel()
                                    ->placeholder('Nombre proveedor 2')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                TextInput::make('proveedor_c_nombre')
                                    ->hiddenLabel()
                                    ->placeholder('Nombre proveedor 3')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('ITEMS A INCLUIR EN EL SUMARIO')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-items'])
                    ->schema([
                        CheckboxList::make('selected_item_ids')
                            ->label('Seleccion parcial de items de la solicitud')
                            ->options(fn (callable $get): array => self::solicitudItemOptions((int) ($get('solicitud_compra_id') ?? 0)))
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
                        Grid::make(22)
                            ->extraAttributes(['class' => 'sdc-table-wide'])
                            ->schema([
                                Placeholder::make('head_blank_a')
                                    ->hiddenLabel()
                                    ->content('')
                                    ->columnSpan(8),
                                Placeholder::make('head_proveedor_a')
                                    ->hiddenLabel()
                                    ->content(fn (callable $get): HtmlString => new HtmlString('<div class="sdc-label-box">' . e((string) ($get('proveedor_a_nombre') ?: 'PROVEEDOR 1')) . '</div>'))
                                    ->columnSpan(4),
                                Placeholder::make('head_proveedor_b')
                                    ->hiddenLabel()
                                    ->content(fn (callable $get): HtmlString => new HtmlString('<div class="sdc-label-box">' . e((string) ($get('proveedor_b_nombre') ?: 'PROVEEDOR 2')) . '</div>'))
                                    ->columnSpan(4),
                                Placeholder::make('head_proveedor_c')
                                    ->hiddenLabel()
                                    ->content(fn (callable $get): HtmlString => new HtmlString('<div class="sdc-label-box">' . e((string) ($get('proveedor_c_nombre') ?: 'PROVEEDOR 3')) . '</div>'))
                                    ->columnSpan(4),
                                Placeholder::make('head_sel')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">SEL</div>'))
                                    ->extraAttributes(['class' => 'sdc-edge-right-cell'])
                                    ->columnSpan(2),
                            ]),

                        Grid::make(22)
                            ->extraAttributes(['class' => 'sdc-table-wide'])
                            ->schema([
                                Placeholder::make('head_item')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">ITEM</div>'))
                                    ->columnSpan(1),
                                Placeholder::make('head_descripcion')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="sdc-label-box">DESCRIPCION</div>'))
                                    ->columnSpan(5),
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
                                    ->content(new HtmlString('<div class="sdc-label-box">SEL</div>'))
                                    ->extraAttributes(['class' => 'sdc-edge-right-cell'])
                                    ->columnSpan(2),
                            ]),

                        Repeater::make('comparativo_items')
                            ->label('Matriz comparativa de items')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default([])
                            ->live()
                            ->afterStateHydrated(function ($state, callable $set): void {
                                $rows = self::recalculateRows($state ?? []);
                                $set('comparativo_items', $rows);
                                self::setColumnTotals($rows, $set);
                            })
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $rows = self::recalculateRows($state ?? []);
                                $set('comparativo_items', $rows);
                                self::setColumnTotals($rows, $set);
                            })
                            ->schema([
                                Grid::make(22)
                                    ->extraAttributes(['class' => 'sdc-table-wide'])
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
                                            ->columnSpan(5),

                                        TextInput::make('unidad_medida')
                                            ->label('UND')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('cantidad')
                                            ->label('CANT')
                                            ->disabled()
                                            ->dehydrated()
                                            ->numeric()
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov1')
                                            ->label('MARCA')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov1')
                                            ->label('P/U')
                                            ->numeric()
                                            ->default(0)
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov1', round($cantidad * $precioUnitario, 2));
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('precio_total_prov1')
                                            ->label('P/T')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov2')
                                            ->label('MARCA')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov2')
                                            ->label('P/U')
                                            ->numeric()
                                            ->default(0)
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov2', round($cantidad * $precioUnitario, 2));
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('precio_total_prov2')
                                            ->label('P/T')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov3')
                                            ->label('MARCA')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov3')
                                            ->label('P/U')
                                            ->numeric()
                                            ->default(0)
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov3', round($cantidad * $precioUnitario, 2));
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('precio_total_prov3')
                                            ->label('P/T')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        Select::make('proveedor_seleccionado')
                                            ->label('SEL')
                                            ->options(fn (callable $get): array => [
                                                'A' => (string) ($get('../../proveedor_a_nombre') ?: 'Proveedor 1'),
                                                'B' => (string) ($get('../../proveedor_b_nombre') ?: 'Proveedor 2'),
                                                'C' => (string) ($get('../../proveedor_c_nombre') ?: 'Proveedor 3'),
                                            ])
                                            ->required()
                                            ->default('A')
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
                                    ->content(new HtmlString('<div class="sdc-label-box">TOTAL COMPRA</div>'))
                                    ->columnSpan(3),

                                TextInput::make('total_compra_prov1')
                                    ->label(fn (callable $get): string => (string) ($get('proveedor_a_nombre') ?: 'Proveedor 1'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(3),

                                TextInput::make('total_compra_prov2')
                                    ->label(fn (callable $get): string => (string) ($get('proveedor_b_nombre') ?: 'Proveedor 2'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(3),

                                TextInput::make('total_compra_prov3')
                                    ->label(fn (callable $get): string => (string) ($get('proveedor_c_nombre') ?: 'Proveedor 3'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
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
                                    ->rows(3)
                                    ->columnSpan(8),

                                Placeholder::make('elaborado_por_preview')
                                    ->label('Elaborado por')
                                    ->content(fn (): string => (string) (auth()->user()?->name ?? 'N/A'))
                                    ->columnSpan(6),

                                Placeholder::make('revisado_por_preview')
                                    ->label('Revisado por')
                                    ->content('Pendiente de revision de Finanzas')
                                    ->columnSpan(6),

                                Placeholder::make('firma_preview')
                                    ->label('Firma')
                                    ->content('Se registra en el flujo de aprobacion')
                                    ->columnSpanFull(),

                                Hidden::make('proveedor_ganador_id'),
                                Hidden::make('estado'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function solicitudCompraOptions(): array
    {
        return SolicitudCompra::query()
            ->whereNotNull('fecha_receptor')
            ->where('estado', '!=', 'RECHAZADA')
            ->orderByDesc('id')
            ->get(['id', 'codigo_control', 'departamento_solicitante'])
            ->mapWithKeys(function (SolicitudCompra $solicitud): array {
                $codigo = $solicitud->codigo_control ?: (string) $solicitud->id;
                $label = $codigo . ' | ' . ($solicitud->departamento_solicitante ?: 'Sin departamento');

                return [$solicitud->id => $label];
            })
            ->all();
    }

    public static function solicitudItemOptions(int $solicitudId): array
    {
        if ($solicitudId <= 0) {
            return [];
        }

        return SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->orderBy('item')
            ->get()
            ->mapWithKeys(function (SolicitudCompraItem $item): array {
                $label = sprintf(
                    '#%s | %s | %s | Cant: %s',
                    (string) ($item->item ?: $item->id),
                    (string) $item->descripcion,
                    (string) $item->unidad_medida,
                    number_format((float) $item->cantidad_a_comprar ?: (float) $item->cantidad_solicitada, 2, ',', '.')
                );

                return [(string) $item->id => $label];
            })
            ->all();
    }

    public static function syncRowsFromSelectedItems(array $selectedIds, array $existingRows, int $solicitudId, callable $set): void
    {
        if ($solicitudId <= 0 || $selectedIds === []) {
            $set('comparativo_items', []);
            self::setColumnTotals([], $set);

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
            $cantidad = (float) ($item->cantidad_a_comprar ?: $item->cantidad_solicitada);

            $rows[] = [
                'solicitud_compra_item_id' => $selectedId,
                'item' => $item->item ?: $item->id,
                'descripcion' => $item->descripcion,
                'unidad_medida' => $item->unidad_medida,
                'cantidad' => $cantidad,
                'marca_prov1' => $existing['marca_prov1'] ?? null,
                'precio_unitario_prov1' => (float) ($existing['precio_unitario_prov1'] ?? 0),
                'precio_total_prov1' => (float) ($existing['precio_total_prov1'] ?? 0),
                'marca_prov2' => $existing['marca_prov2'] ?? null,
                'precio_unitario_prov2' => (float) ($existing['precio_unitario_prov2'] ?? 0),
                'precio_total_prov2' => (float) ($existing['precio_total_prov2'] ?? 0),
                'marca_prov3' => $existing['marca_prov3'] ?? null,
                'precio_unitario_prov3' => (float) ($existing['precio_unitario_prov3'] ?? 0),
                'precio_total_prov3' => (float) ($existing['precio_total_prov3'] ?? 0),
                'proveedor_seleccionado' => $existing['proveedor_seleccionado'] ?? 'A',
            ];
        }

        $rows = self::recalculateRows($rows);

        $set('comparativo_items', $rows);
        self::setColumnTotals($rows, $set);
    }

    public static function recalculateRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                $cantidad = (float) ($row['cantidad'] ?? 0);

                $precioUnitario1 = (float) ($row['precio_unitario_prov1'] ?? 0);
                $precioUnitario2 = (float) ($row['precio_unitario_prov2'] ?? 0);
                $precioUnitario3 = (float) ($row['precio_unitario_prov3'] ?? 0);

                $row['precio_total_prov1'] = round($cantidad * $precioUnitario1, 2);
                $row['precio_total_prov2'] = round($cantidad * $precioUnitario2, 2);
                $row['precio_total_prov3'] = round($cantidad * $precioUnitario3, 2);

                return $row;
            })
            ->values()
            ->all();
    }

    public static function setColumnTotals(array $rows, callable $set): void
    {
        $totalProv1 = 0.0;
        $totalProv2 = 0.0;
        $totalProv3 = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $totalProv1 += (float) ($row['precio_total_prov1'] ?? 0);
            $totalProv2 += (float) ($row['precio_total_prov2'] ?? 0);
            $totalProv3 += (float) ($row['precio_total_prov3'] ?? 0);
        }

        $set('total_compra_prov1', round($totalProv1, 2));
        $set('total_compra_prov2', round($totalProv2, 2));
        $set('total_compra_prov3', round($totalProv3, 2));
    }
}
