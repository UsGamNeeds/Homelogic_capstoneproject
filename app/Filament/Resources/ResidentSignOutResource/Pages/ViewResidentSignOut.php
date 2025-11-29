<?php

namespace App\Filament\Resources\ResidentSignOutResource\Pages;

use App\Filament\Resources\ResidentSignOutResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewResidentSignOut extends ViewRecord
{
    protected static string $resource = ResidentSignOutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
