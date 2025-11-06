<?php

namespace App\Filament\Resources\IncidentResource\Pages;

use App\Filament\Resources\IncidentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIncident extends CreateRecord
{
    protected static string $resource = IncidentResource::class;

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        
        // Add confirmation to the create/save button
        foreach ($actions as $action) {
            if ($action instanceof Actions\CreateAction || $action->getName() === 'create') {
                $action->requiresConfirmation()
                    ->modalHeading('Create Incident')
                    ->modalDescription('Are you sure you want to create this incident?')
                    ->modalSubmitActionLabel('Yes, Create');
                break;
            }
        }
        
        return $actions;
    }
}
