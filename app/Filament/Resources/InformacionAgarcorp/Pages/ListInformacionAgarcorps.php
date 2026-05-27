<?php

namespace App\Filament\Resources\InformacionAgarcorp\Pages;

use App\Filament\Resources\InformacionAgarcorp\InformacionAgarcorpResource;
use App\Models\InformacionAgarcorp;
use Filament\Resources\Pages\Page;

class ListInformacionAgarcorps extends Page
{
    protected static string $resource = InformacionAgarcorpResource::class;

    protected static ?string $title = 'Información AGARCORP';

    protected string $view = 'filament.resources.informacion-agarcorp.pages.list-informacion-agarcorps';

    public InformacionAgarcorp $record;

    public function mount(): void
    {
        $this->record = InformacionAgarcorp::current();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
