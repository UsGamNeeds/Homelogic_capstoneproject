<?php

namespace App\Filament\Resources\ResidentSignOutResource\Pages;

use App\Filament\Resources\ResidentSignOutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResidentSignOuts extends ListRecords
{
    protected static string $resource = ResidentSignOutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
