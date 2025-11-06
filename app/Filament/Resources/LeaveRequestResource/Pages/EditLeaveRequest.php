<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If staff_id is being updated, update branch_id accordingly
        if (isset($data['staff_id']) && $data['staff_id'] != $this->record->staff_id) {
            $staff = \App\Models\User::find($data['staff_id']);
            if ($staff && $staff->assigned_branch_id) {
                $data['branch_id'] = $staff->assigned_branch_id;
            }
        }

        return $data;
    }

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
                    ->modalHeading('Save Leave Request')
                    ->modalDescription('Are you sure you want to save your changes?')
                    ->modalSubmitActionLabel('Yes, Save');
                break;
            }
        }
        
        return $actions;
    }
}
