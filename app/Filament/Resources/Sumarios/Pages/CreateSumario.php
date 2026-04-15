<?php

namespace App\Filament\Resources\Sumarios\Pages;

use App\Filament\Resources\Sumarios\SumarioResource;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateSumario extends CreateRecord
{
    protected static string $resource = SumarioResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            \Filament\Actions\Action::make('saveDraft')
                ->label('Guardar como borrador')
                ->color('warning')
                ->action(function () {
                    $data = $this->form->getRawState();
                    $data['estado'] = 'BORRADOR';
                    
                    $record = $this->handleRecordCreation($this->mutateFormDataBeforeCreate($data));
                    
                    $this->record = $record;
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Borrador de sumario guardado')
                        ->body('Tu sumario ha sido guardado exitosamente como BORRADOR en la lista.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected Width | string | null $maxWidth = Width::Full;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $rows = self::normalizeRows($data['comparativo_items'] ?? []);

        $data['total_compra_prov1'] = round(collect($rows)->sum(fn (array $row): float => (float) ($row['precio_total_prov1'] ?? 0)), 2);
        $data['total_compra_prov2'] = round(collect($rows)->sum(fn (array $row): float => (float) ($row['precio_total_prov2'] ?? 0)), 2);
        $data['total_compra_prov3'] = round(collect($rows)->sum(fn (array $row): float => (float) ($row['precio_total_prov3'] ?? 0)), 2);

        $data['estado'] = 'BORRADOR';
        $data['workflow_estado'] = 'BORRADOR';
        $data['elaborado_por_user_id'] = auth()->id();
        $data['proveedor_ganador_id'] = null;

        if (blank($data['departamento_solicitante'] ?? null) && filled($data['solicitud_compra_id'] ?? null)) {
            $data['departamento_solicitante'] = SolicitudCompra::query()
                ->whereKey($data['solicitud_compra_id'])
                ->value('departamento_solicitante');
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $rows = self::normalizeRows($data['comparativo_items'] ?? []);

        $proveedorA = trim((string) ($data['proveedor_a_nombre'] ?? ''));
        $proveedorB = trim((string) ($data['proveedor_b_nombre'] ?? ''));
        $proveedorC = trim((string) ($data['proveedor_c_nombre'] ?? ''));

        unset(
            $data['selected_item_ids'],
            $data['comparativo_items'],
            $data['proveedor_a_nombre'],
            $data['proveedor_b_nombre'],
            $data['proveedor_c_nombre']
        );

        return DB::transaction(function () use ($data, $rows, $proveedorA, $proveedorB, $proveedorC): Sumario {
            /** @var Sumario $sumario */
            $sumario = Sumario::query()->create($data);

            $itemIds = [];

            foreach ($rows as $row) {
                $sumarioItem = SumarioItem::query()->create([
                    'sumario_id' => $sumario->id,
                    'solicitud_compra_item_id' => $row['solicitud_compra_item_id'],
                    'item' => $row['item'] ?? null,
                    'descripcion' => $row['descripcion'] ?? '',
                    'unidad_medida' => $row['unidad_medida'] ?? 'UND',
                    'cantidad' => (float) ($row['cantidad'] ?? 0),
                ]);

                $itemIds[] = (int) $row['solicitud_compra_item_id'];

                $selectedColumn = strtoupper((string) ($row['proveedor_seleccionado'] ?? 'A'));

                $this->createOption($sumarioItem, 1, $proveedorA, $row['marca_prov1'] ?? null, (float) ($row['precio_unitario_prov1'] ?? 0), (float) ($row['precio_total_prov1'] ?? 0), $selectedColumn === 'A');
                $this->createOption($sumarioItem, 2, $proveedorB, $row['marca_prov2'] ?? null, (float) ($row['precio_unitario_prov2'] ?? 0), (float) ($row['precio_total_prov2'] ?? 0), $selectedColumn === 'B');
                $this->createOption($sumarioItem, 3, $proveedorC, $row['marca_prov3'] ?? null, (float) ($row['precio_unitario_prov3'] ?? 0), (float) ($row['precio_total_prov3'] ?? 0), $selectedColumn === 'C');
            }

            if ($itemIds !== []) {
                SolicitudCompraItem::query()
                    ->whereIn('id', $itemIds)
                    ->where('estado_item', '!=', 'EN_OC')
                    ->update(['estado_item' => 'EN_SUMARIO']);
            }

            SolicitudCompra::query()
                ->whereKey($sumario->solicitud_compra_id)
                ->update(['estado' => 'SUMARIO_EN_REVISION']);

            return $sumario;
        });
    }

    private function createOption(SumarioItem $sumarioItem, int $numero, string $proveedorNombre, ?string $marca, float $precioUnitario, float $precioTotal, bool $selected): void
    {
        SumarioItemOpcion::query()->create([
            'sumario_item_id' => $sumarioItem->id,
            'opcion_numero' => $numero,
            'proveedor_id' => $this->resolveProveedorIdByName($proveedorNombre),
            'proveedor_nombre' => $proveedorNombre,
            'marca' => $marca,
            'precio_unitario' => round($precioUnitario, 2),
            'precio_total' => round($precioTotal, 2),
            'seleccionada' => $selected,
        ]);
    }

    private function resolveProveedorIdByName(string $nombre): ?int
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return null;
        }

        return Proveedor::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->value('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row): bool => is_array($row) && filled($row['solicitud_compra_item_id'] ?? null))
            ->map(function (array $row): array {
                $cantidad = (float) ($row['cantidad'] ?? 0);

                $precioUnitario1 = (float) ($row['precio_unitario_prov1'] ?? 0);
                $precioUnitario2 = (float) ($row['precio_unitario_prov2'] ?? 0);
                $precioUnitario3 = (float) ($row['precio_unitario_prov3'] ?? 0);

                return [
                    ...$row,
                    'cantidad' => $cantidad,
                    'precio_unitario_prov1' => $precioUnitario1,
                    'precio_total_prov1' => round($cantidad * $precioUnitario1, 2),
                    'precio_unitario_prov2' => $precioUnitario2,
                    'precio_total_prov2' => round($cantidad * $precioUnitario2, 2),
                    'precio_unitario_prov3' => $precioUnitario3,
                    'precio_total_prov3' => round($cantidad * $precioUnitario3, 2),
                    'proveedor_seleccionado' => strtoupper((string) ($row['proveedor_seleccionado'] ?? 'A')),
                ];
            })
            ->values()
            ->all();
    }
}
