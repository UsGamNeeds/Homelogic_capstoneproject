<?php

namespace App\Filament\Resources\SleepPatternResource\Pages;

use App\Filament\Resources\SleepPatternResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSleepPattern extends EditRecord
{
    protected static string $resource = SleepPatternResource::class;

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
                    ->modalHeading('Save Sleep Pattern')
                    ->modalDescription('Are you sure you want to save your changes?')
                    ->modalSubmitActionLabel('Yes, Save');
                break;
            }
        }
        
        return $actions;
    }
}
