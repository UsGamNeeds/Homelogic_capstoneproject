<?php

namespace App\Filament\Resources\SleepRecordResource\Pages;

use App\Filament\Resources\SleepRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSleepRecord extends EditRecord
{
    protected static string $resource = SleepRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        
        // Add confirmation to the save button
        foreach ($actions as $action) {
            if ($action instanceof Actions\SaveAction || $action->getName() === 'save') {
                $action->requiresConfirmation()
                    ->modalHeading('Save Sleep Record')
                    ->modalDescription('Are you sure you want to save your changes?')
                    ->modalSubmitActionLabel('Yes, Save');
                break;
            }
        }
        
        return $actions;
    }
}
