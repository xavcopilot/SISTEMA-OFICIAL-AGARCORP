<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SumarioProviderGrouping;
use PHPUnit\Framework\TestCase;

class SumarioProviderGroupingTest extends TestCase
{
    public function test_duplicate_provider_labels_are_numbered_left_to_right(): void
    {
        $options = SumarioProviderGrouping::selectionOptions([
            1 => 'Tecno Suministros Orion',
            2 => 'Distribuidora Maxis C.A.',
            3 => 'Tecno Suministros Orion',
        ]);

        $this->assertSame('Tecno Suministros Orion (Tipo 1)', $options['A']);
        $this->assertSame('Distribuidora Maxis C.A.', $options['B']);
        $this->assertSame('Tecno Suministros Orion (Tipo 2)', $options['C']);
    }

    public function test_duplicate_providers_merge_totals_into_unique_slots(): void
    {
        $totals = SumarioProviderGrouping::groupedTotalsFromRows([
            1 => 'Tecno Suministros Orion',
            2 => 'Distribuidora Maxis C.A.',
            3 => 'Tecno Suministros Orion',
        ], [
            [
                'proveedor_seleccionado' => 'A',
                'precio_total_prov1' => 120,
                'precio_total_prov2' => 0,
                'precio_total_prov3' => 0,
            ],
            [
                'proveedor_seleccionado' => 'C',
                'precio_total_prov1' => 0,
                'precio_total_prov2' => 0,
                'precio_total_prov3' => 80,
            ],
            [
                'proveedor_seleccionado' => 'B',
                'precio_total_prov1' => 0,
                'precio_total_prov2' => 50,
                'precio_total_prov3' => 0,
            ],
        ]);

        $this->assertSame(200.0, $totals[1]);
        $this->assertSame(50.0, $totals[2]);
        $this->assertSame(0.0, $totals[3]);
    }
}