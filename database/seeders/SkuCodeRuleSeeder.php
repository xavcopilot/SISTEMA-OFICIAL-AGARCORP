<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SkuCodeRule;
use Illuminate\Database\Seeder;

class SkuCodeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()->orderBy('name')->get();

        foreach ($categories as $category) {
            $prefix = SkuCodeRule::normalizePrefix((string) $category->name);
            $lastSku = Product::query()
                ->where('sku', 'like', $prefix . '-%')
                ->orderByDesc('sku')
                ->value('sku');

            $nextCorrelative = 1;

            if ($lastSku && preg_match('/-(\d+)$/', (string) $lastSku, $matches) === 1) {
                $nextCorrelative = ((int) $matches[1]) + 1;
            }

            $existingNext = (int) (SkuCodeRule::query()
                ->where('category_id', $category->id)
                ->value('next_correlative') ?? 1);

            SkuCodeRule::updateOrCreate(
                ['category_id' => $category->id],
                [
                    'prefix' => $prefix,
                    'next_correlative' => max($existingNext, $nextCorrelative),
                    'number_length' => 4,
                    'is_active' => true,
                    'notes' => null,
                ]
            );
        }

        $this->command?->info('Reglas de codificacion SKU generadas.');
    }
}
