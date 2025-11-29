<?php

namespace App\Filament\Resources\ResidentSignOutResource\Pages;

use App\Filament\Resources\ResidentSignOutResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResidentSignOut extends EditRecord
{
    protected static string $resource = ResidentSignOutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
