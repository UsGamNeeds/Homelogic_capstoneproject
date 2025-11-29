<?php

namespace App\Filament\Resources\StaffClockInResource\Pages;

use App\Filament\Resources\StaffClockInResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaffClockIn extends EditRecord
{
    protected static string $resource = StaffClockInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

