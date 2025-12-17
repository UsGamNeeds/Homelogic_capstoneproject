<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        
        // If staff_id is not provided, assume user is creating for themselves
        if (!isset($data['staff_id'])) {
            $data['staff_id'] = $user->id;
        }
        
        // Get branch_id from the selected staff member
        $staff = \App\Models\User::find($data['staff_id']);
        if ($staff && $staff->assigned_branch_id) {
            $data['branch_id'] = $staff->assigned_branch_id;
        } else {
            // If staff doesn't have an assigned branch, show error
            $isSelf = $data['staff_id'] == $user->id;
            $message = $isSelf 
                ? 'You must have an assigned branch to create a leave request. Please contact your administrator.'
                : 'The selected staff member must have an assigned branch. Please assign a branch to this staff member first.';
            
            \Filament\Notifications\Notification::make()
                ->title('Cannot Create Leave Request')
                ->body($message)
                ->danger()
                ->send();
            
            $field = $isSelf ? 'branch_id' : 'staff_id';
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                [$field => [$message]]
            );
        }
        
        // If user is creating for themselves, ensure status is pending
        if ($data['staff_id'] == $user->id) {
            $data['status'] = 'pending';
        }

        return $data;
    }

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        
        // Add confirmation to the create/save button
        foreach ($actions as $action) {
            if ($action instanceof Actions\CreateAction || $action->getName() === 'create') {
                $action->requiresConfirmation()
                    ->modalHeading('Create Leave Request')
                    ->modalDescription('Are you sure you want to create this leave request?')
                    ->modalSubmitActionLabel('Yes, Create');
                break;
            }
        }
        
        return $actions;
    }
}
