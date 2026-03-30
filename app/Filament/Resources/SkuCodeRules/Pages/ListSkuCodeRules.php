<?php

namespace App\Filament\Resources\SkuCodeRules\Pages;

use App\Filament\Resources\SkuCodeRules\SkuCodeRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSkuCodeRules extends ListRecords
{
    protected static string $resource = SkuCodeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
