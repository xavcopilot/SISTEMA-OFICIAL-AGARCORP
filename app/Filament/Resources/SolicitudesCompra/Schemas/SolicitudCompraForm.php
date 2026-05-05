<?php

namespace App\Filament\Resources\SolicitudesCompra\Schemas;

use App\Models\Product;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Support\SolicitudCompraFlow;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SolicitudCompraForm
{
    private const SEARCH_TRANSLATE_FROM = 'áéíóúàèìòùäëïöüâêîôûñç';

    private const SEARCH_TRANSLATE_TO = 'aeiouaeiouaeiouaeiounc';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('solicitado_por_user_id')
                    ->default(fn () => auth()->id())
                    ->required(),

                Section::make('Encabezado de solicitud')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('codigo_control')
                                    ->label('N° de control')
                                    ->placeholder('Se definirá al firmar la solicitud')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('codigo_control_procura')
                                    ->label('N° de control procura')
                                    ->placeholder('Se definirá por Procura')
                                    ->disabled()
                                    ->dehydrated(false),

                                DatePicker::make('fecha_solicitud')
                                    ->label('Fecha')
                                    ->default(now())
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('tipo_solicitud')
                                    ->label('Tipo de solicitud')
                                    ->options([
                                        'Material' => 'Material',
                                        'Servicio' => 'Servicio',
                                    ])
                                    ->required(),

                                Select::make('prioridad')
                                    ->label('Prioridad')
                                    ->options([
                                        'Alta' => 'Alta',
                                        'Media' => 'Media',
                                        'Baja' => 'Baja',
                                    ])
                                    ->placeholder('Asignado por aprobador al firmar')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('departamento_solicitante')
                                    ->label('Departamento solicitante')
                                    ->default(fn () => auth()->user()?->departamento?->nombre)
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                            ]),

                        Textarea::make('para_ser_usado_en')
                            ->label('Para ser usado en')
                            ->rows(2),
                    ])
                    ->columnSpanFull(),

                Section::make('Solicitud rechazada')
                    ->description('Revisa este comentario, corrige la solicitud y vuelve a enviarla.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('rechazo_etapa')
                                    ->label('Rechazada en etapa')
                                    ->formatStateUsing(fn ($state) => $state ? strtoupper((string) $state) : null)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('rechazo_por_nombre')
                                    ->label('Rechazada por')
                                    ->formatStateUsing(fn ($state, ?SolicitudCompra $record) => $state ?: $record?->rechazoPor?->name)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('rechazo_en')
                                    ->label('Fecha de rechazo')
                                    ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->format('d/m/Y H:i') : null)
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Textarea::make('rechazo_comentario')
                            ->label('Comentario de rechazo')
                            ->rows(4)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->visible(fn (?SolicitudCompra $record): bool => filled($record?->rechazo_comentario))
                    ->columnSpanFull(),

                Section::make('Materiales / servicios solicitados')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('Detalle')
                            ->defaultItems(1)
                            ->maxItems(15)
                            ->reorderable(false)
                            ->addActionLabel('Añadir detalle')
                            ->helperText('Maximo permitido: 15 articulos por solicitud.')
                            ->afterStateHydrated(function (callable $set, ?array $state): void {
                                $state ??= [];

                                $counter = 1;
                                foreach ($state as &$row) {
                                    if (! is_array($row)) {
                                        continue;
                                    }

                                    $row['item'] = $counter++;
                                    $row['cantidad_a_comprar'] = self::calculateCantidadAComprar(
                                        $row['cantidad_solicitada'] ?? 0,
                                        $row['cantidad_existencia'] ?? 0,
                                    );
                                }

                                $set('items', $state);
                            })
                            ->afterStateUpdated(function (callable $set, ?array $state): void {
                                $state ??= [];

                                $counter = 1;
                                foreach ($state as &$row) {
                                    if (! is_array($row)) {
                                        continue;
                                    }

                                    $row['item'] = $counter++;
                                    $row['cantidad_a_comprar'] = self::calculateCantidadAComprar(
                                        $row['cantidad_solicitada'] ?? 0,
                                        $row['cantidad_existencia'] ?? 0,
                                    );
                                }

                                $set('items', $state);
                            })
                            ->dehydrateStateUsing(function (?array $state): array {
                                $state ??= [];

                                $counter = 1;
                                foreach ($state as &$row) {
                                    if (! is_array($row)) {
                                        continue;
                                    }

                                    $row['item'] = $counter++;
                                    $row['cantidad_a_comprar'] = self::calculateCantidadAComprar(
                                        $row['cantidad_solicitada'] ?? 0,
                                        $row['cantidad_existencia'] ?? 0,
                                    );
                                }

                                return $state;
                            })
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        TextInput::make('item')
                                            ->label('Item')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        Select::make('descripcion')
                                            ->label('Descripción')
                                            ->native(false)
                                            ->searchable()
                                            ->live()
                                            ->getSearchResultsUsing(function (string $search): array {
                                                $search = trim($search);

                                                $options = self::inventoryDescriptionOptions(
                                                    auth()->user()?->departamento?->nombre,
                                                    $search
                                                );

                                                if ($search === '') {
                                                    return $options;
                                                }

                                                if (! array_key_exists($search, $options)) {
                                                    $options = [
                                                        $search => 'Usar texto: ' . $search,
                                                        ...$options,
                                                    ];
                                                }

                                                return $options;
                                            })
                                            ->getOptionLabelUsing(fn (?string $value): ?string => filled($value) ? self::extractDescriptionFromSuggestion($value) : null)
                                            ->extraAttributes(['class' => 'ag-desc-suggest-field'])
                                            ->helperText('Escribe para ver sugerencias de Almacen de tu departamento. Si no existe, puedes escribir un material nuevo.')
                                            ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                                $normalizedState = self::normalizeSelectedDescriptionState(
                                                    $state,
                                                    auth()->user()?->departamento?->nombre,
                                                );

                                                if ($normalizedState !== (string) ($state ?? '')) {
                                                    $set('descripcion', $normalizedState);
                                                }

                                                $inventoryData = self::resolveInventoryDataForInput(
                                                    $normalizedState,
                                                    auth()->user()?->departamento?->nombre
                                                );

                                                $descripcionNormalizada = $inventoryData['descripcion'];
                                                if ($descripcionNormalizada !== '' && $descripcionNormalizada !== $normalizedState) {
                                                    $set('descripcion', $descripcionNormalizada);
                                                }

                                                if (filled($inventoryData['unidad_medida'])) {
                                                    $set('unidad_medida', $inventoryData['unidad_medida']);
                                                }

                                                $existencia = (float) $inventoryData['stock_actual'];
                                                $set('cantidad_existencia', $existencia);

                                                $set('cantidad_a_comprar', self::calculateCantidadAComprar(
                                                    $get('cantidad_solicitada') ?? 0,
                                                    $existencia,
                                                ));
                                            })
                                            ->required()
                                            ->columnSpan(4),

                                        TextInput::make('unidad_medida')
                                            ->label('UND')
                                            ->default('UND')
                                            ->required()
                                            ->columnSpan(1),

                                        TextInput::make('cantidad_solicitada')
                                            ->label('Solicitada')
                                            ->numeric()
                                            ->live(debounce: 250)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $set('cantidad_a_comprar', self::calculateCantidadAComprar(
                                                    $state ?? 0,
                                                    $get('cantidad_existencia') ?? 0,
                                                ));
                                            })
                                            ->required()
                                            ->columnSpan(2),

                                        TextInput::make('cantidad_existencia')
                                            ->label('En existencia')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(0)
                                            ->columnSpan(2),

                                        TextInput::make('cantidad_a_comprar')
                                            ->label('A comprar')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos administrativos')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('centro')->label('Centro'),
                                TextInput::make('elemento')->label('Elemento'),
                                TextInput::make('cuenta')->label('Cuenta'),
                                TextInput::make('contrato')->label('Contrato'),
                            ]),

                        Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'BORRADOR' => 'BORRADOR',
                                'RECHAZADA' => 'RECHAZADA',
                                'EN_ESPERA_ALMACEN' => 'En espera Almacen',
                                'EN_ESPERA_APROBADOR' => 'En espera Aprobador',
                                'EN_ESPERA_PROCURA' => 'En espera Procura',
                                'RECIBIDO_POR_PROCURA' => 'Recibido por Procura',
                            ])
                            ->default('BORRADOR')
                            ->disabled()
                            ->dehydrated(false),

                        Section::make('Firmas y responsables')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('solicitado_por_nombre')
                                            ->label('Solicitado por')
                                            ->default(fn (?SolicitudCompra $record) => $record?->solicitadoPor?->name ?? auth()->user()?->name)
                                            ->formatStateUsing(fn ($state, ?SolicitudCompra $record) => $state ?: ($record?->solicitadoPor?->name ?? auth()->user()?->name))
                                            ->disabled()
                                            ->dehydrated(false),

                                        Select::make('por_almacen_user_id')
                                            ->label('Por almacén')
                                            ->relationship('porAlmacen', 'name', fn (Builder $query) => SolicitudCompraFlow::limitToStorageUsers($query))
                                            ->default(fn (?SolicitudCompra $record) => $record?->por_almacen_user_id ?? SolicitudCompraFlow::defaultAlmacenUserId())
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->required()
                                            ->helperText('Selecciona el usuario de almacen que recibira esta solicitud.')
                                            ->afterStateHydrated(function ($state, callable $set): void {
                                                $set('cargo_almacen', SolicitudCompraFlow::cargoForUserId($state ?: SolicitudCompraFlow::defaultAlmacenUserId()));
                                            })
                                            ->afterStateUpdated(function ($state, callable $set): void {
                                                $set('cargo_almacen', SolicitudCompraFlow::cargoForUserId($state));
                                            })
                                            ->dehydrated(),

                                        Select::make('aprobado_por_user_id')
                                            ->label('Aprobado por')
                                            ->relationship('aprobadoPor', 'name', fn (Builder $query) => SolicitudCompraFlow::limitToApprovers($query))
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->required()
                                            ->helperText('Selecciona al lider o gerencia que debe aprobar esta solicitud.')
                                            ->afterStateHydrated(function ($state, callable $set): void {
                                                $set('cargo_aprobador', SolicitudCompraFlow::cargoForUserId($state));
                                            })
                                            ->afterStateUpdated(function ($state, callable $set): void {
                                                $set('cargo_aprobador', SolicitudCompraFlow::cargoForUserId($state));
                                            }),

                                        Select::make('recibido_por_user_id')
                                            ->label('Recibido por')
                                            ->relationship('recibidoPor', 'name', fn (Builder $query) => SolicitudCompraFlow::limitToProcurementUsers($query))
                                            ->default(fn (?SolicitudCompra $record) => $record?->recibido_por_user_id ?? SolicitudCompraFlow::defaultProcuraUserId())
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->required()
                                            ->helperText('Selecciona el usuario de procura que recibira esta solicitud.')
                                            ->afterStateHydrated(function ($state, callable $set): void {
                                                $set('cargo_receptor', SolicitudCompraFlow::cargoForUserId($state ?: SolicitudCompraFlow::defaultProcuraUserId()));
                                            })
                                            ->afterStateUpdated(function ($state, callable $set): void {
                                                $set('cargo_receptor', SolicitudCompraFlow::cargoForUserId($state));
                                            })
                                            ->dehydrated(),
                                    ]),

                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('cargo_solicitante')
                                            ->label('Cargo solicitante')
                                            ->default(fn ($record) => $record?->cargo_solicitante ?? auth()->user()?->cargo?->nombre)
                                            ->readOnly(),
                                        TextInput::make('cargo_almacen')
                                            ->label('Cargo almacén')
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('cargo_aprobador')
                                            ->label('Cargo aprobador')
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('cargo_receptor')
                                            ->label('Cargo receptor')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),

                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('firma_solicitante')
                                            ->label('Firma solicitante')
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('firma_almacen')
                                            ->label('Firma almacén')
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('firma_aprobador')
                                            ->label('Firma aprobador')
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('firma_receptor')
                                            ->label('Firma receptor')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),

                                Grid::make(5)
                                    ->schema([
                                        DatePicker::make('fecha_solicitante')
                                            ->label('Fecha solicitante')
                                            ->default(now())
                                            ->disabled()
                                            ->dehydrated(false),

                                        DatePicker::make('fecha_almacen')
                                            ->label('Fecha almacén')
                                            ->disabled()
                                            ->dehydrated(false),

                                        DatePicker::make('fecha_aprobador')
                                            ->label('Fecha aprobador')
                                            ->disabled()
                                            ->dehydrated(false),

                                        DatePicker::make('fecha_receptor')
                                            ->label('Fecha receptor')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('hora_receptor')
                                            ->label('Hora receptor')
                                            ->type('time')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                                    ])
                                    ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Opciones del select de descripcion: clave = descripcion limpia, valor = etiqueta extendida.
     */
    private static function inventoryDescriptionOptions(?string $departmentName, ?string $search = null): array
    {
        $query = Product::query()
            ->where('is_archived', false)
            ->whereNotNull('descripcion')
            ->whereRaw("trim(descripcion) <> ''");

        $search = trim((string) ($search ?? ''));
        if ($search !== '') {
            $query->where('descripcion', 'ilike', '%' . $search . '%');
        }

        $query
            ->orderBy('descripcion')
            ->limit(500);

        self::applyDepartmentScope($query, $departmentName);

        $aggregated = $query
            ->get(['descripcion', 'medida', 'stock_actual'])
            ->reduce(function (array $carry, Product $product): array {
                $description = trim((string) $product->descripcion);
                if ($description === '') {
                    return $carry;
                }

                $normalized = self::normalizeForInventorySearch($description);
                if ($normalized === '') {
                    return $carry;
                }

                if (! isset($carry[$normalized])) {
                    $carry[$normalized] = [
                        'descripcion' => $description,
                        'stock_actual' => 0.0,
                        'unidades' => [],
                    ];
                }

                if (mb_strlen($description) > mb_strlen((string) $carry[$normalized]['descripcion'])) {
                    $carry[$normalized]['descripcion'] = $description;
                }

                $carry[$normalized]['stock_actual'] += (float) ($product->stock_actual ?? 0);

                $unidad = trim((string) ($product->medida ?? ''));
                if ($unidad !== '') {
                    $carry[$normalized]['unidades'][$unidad] = true;
                }

                return $carry;
            }, []);

        return collect($aggregated)
            ->mapWithKeys(function (array $item): array {
                $units = array_keys($item['unidades']);
                $unitLabel = count($units) === 1 ? $units[0] : (count($units) > 1 ? 'VARIOS' : 'UND');

                $description = (string) $item['descripcion'];
                $label = self::buildSuggestionLabel(
                    $description,
                    $unitLabel,
                    (float) $item['stock_actual']
                );

                return [$description => $label];
            })
            ->sortKeysUsing(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->all();
    }

    private static function inventoryDescriptionOptionLabel(?string $description, ?string $departmentName): ?string
    {
        $description = trim((string) ($description ?? ''));

        if ($description === '') {
            return null;
        }

        $options = self::inventoryDescriptionOptions($departmentName, $description);

        return $options[$description] ?? $description;
    }

    /**
     * Resuelve descripcion, unidad y stock desde texto libre o desde una opcion de sugerencia.
     */
    private static function resolveInventoryDataForInput(?string $input, ?string $departmentName): array
    {
        $description = self::extractDescriptionFromSuggestion($input);
        $normalized = self::normalizeForInventorySearch($description);

        if ($normalized === '') {
            return [
                'descripcion' => '',
                'unidad_medida' => null,
                'stock_actual' => 0.0,
            ];
        }

        $query = Product::query()
            ->where('is_archived', false)
            ->whereRaw(
                "translate(lower(descripcion), ?, ?) = ?",
                [self::SEARCH_TRANSLATE_FROM, self::SEARCH_TRANSLATE_TO, $normalized]
            );

        self::applyDepartmentScope($query, $departmentName);

        $rows = $query->get(['descripcion', 'medida', 'stock_actual']);

        $stock = (float) $rows->sum(fn (Product $product): float => (float) ($product->stock_actual ?? 0));
        $units = $rows
            ->map(fn (Product $product): string => trim((string) ($product->medida ?? '')))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $unitLabel = null;
        if ($units->count() === 1) {
            $unitLabel = (string) $units->first();
        } elseif ($units->count() > 1) {
            $unitLabel = 'VARIOS';
        }

        $canonicalDescription = $rows
            ->map(fn (Product $product): string => trim((string) $product->descripcion))
            ->filter(fn (string $value): bool => $value !== '')
            ->sortByDesc(fn (string $value): int => mb_strlen($value))
            ->first() ?: $description;

        return [
            'descripcion' => $canonicalDescription,
            'unidad_medida' => $unitLabel,
            'stock_actual' => $stock,
        ];
    }

    private static function extractDescriptionFromSuggestion(?string $value): string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return '';
        }

        $parts = explode(' || ', $raw, 2);

        return trim((string) ($parts[0] ?? ''));
    }

    private static function normalizeSelectedDescriptionState(?string $value, ?string $departmentName): string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return '';
        }

        if (str_starts_with($raw, 'Usar texto: ')) {
            return trim(substr($raw, strlen('Usar texto: ')));
        }

        if (str_contains($raw, ' || ')) {
            return self::extractDescriptionFromSuggestion($raw);
        }

        $options = self::inventoryDescriptionOptions($departmentName, $raw);
        $description = array_search($raw, $options, true);

        if (is_string($description) && $description !== '') {
            return $description;
        }

        return $raw;
    }

    private static function buildSuggestionLabel(string $description, string $unit, float $stock): string
    {
        $stockText = number_format($stock, 2, '.', '');
        $stockText = rtrim(rtrim($stockText, '0'), '.');
        $stockText = $stockText === '' ? '0' : $stockText;

        return sprintf('%s || MEDIDA: %s || DISP: %s', $description, $unit, $stockText);
    }

    private static function calculateCantidadAComprar(mixed $cantidadSolicitada, mixed $cantidadExistencia): float
    {
        $solicitada = self::toNumeric($cantidadSolicitada);
        $existencia = self::toNumeric($cantidadExistencia);

        return max($solicitada - $existencia, 0);
    }

    private static function toNumeric(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '') {
            return 0.0;
        }

        $normalized = str_replace([' ', "\t"], '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private static function applyDepartmentScope(Builder $query, ?string $departmentName): void
    {
        $normalizedDepartment = self::normalizeForInventorySearch($departmentName);

        if ($normalizedDepartment === '') {
            return;
        }

        $query
            ->whereNotNull('dpto_responsable')
            ->whereRaw(
                "translate(lower(dpto_responsable), ?, ?) = ?",
                [self::SEARCH_TRANSLATE_FROM, self::SEARCH_TRANSLATE_TO, $normalizedDepartment]
            );
    }

    private static function normalizeForInventorySearch(?string $value): string
    {
        return (string) Str::of((string) ($value ?? ''))
            ->trim()
            ->lower()
            ->ascii();
    }
}
