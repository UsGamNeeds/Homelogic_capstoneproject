<?php

namespace App\Filament\Resources\StaffClockInResource\Pages;

use App\Filament\Resources\StaffClockInResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStaffClockIn extends ViewRecord
{
    protected static string $resource = StaffClockInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

