<?php

namespace App\Filament\Resources\BehaviorResource\Pages;

use App\Filament\Resources\BehaviorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBehavior extends CreateRecord
{
    protected static string $resource = BehaviorResource::class;

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        
        // Add confirmation to the create/save button
        foreach ($actions as $action) {
            if ($action instanceof Actions\CreateAction || $action->getName() === 'create') {
                $action->requiresConfirmation()
                    ->modalHeading('Create Behavior')
                    ->modalDescription('Are you sure you want to create this behavior?')
                    ->modalSubmitActionLabel('Yes, Create');
                break;
            }
        }
        
        return $actions;
    }
}
