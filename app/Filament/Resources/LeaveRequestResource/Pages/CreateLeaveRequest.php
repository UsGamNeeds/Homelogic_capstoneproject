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
        
        // If user is a caregiver, pre-fill their staff_id
        if ($user->hasRole('caregiver')) {
            $data['staff_id'] = $user->id;
            // Set branch_id from user's assigned branch
            if ($user->assigned_branch_id) {
                $data['branch_id'] = $user->assigned_branch_id;
            } else {
                // If caregiver doesn't have an assigned branch, show error
                \Filament\Notifications\Notification::make()
                    ->title('Cannot Create Leave Request')
                    ->body('You must have an assigned branch to create a leave request. Please contact your administrator.')
                    ->danger()
                    ->send();
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['branch_id' => ['You must have an assigned branch to create a leave request.']]
                );
            }
        } else {
            // For admins, get branch_id from the selected staff member
            if (isset($data['staff_id'])) {
                $staff = \App\Models\User::find($data['staff_id']);
                if ($staff && $staff->assigned_branch_id) {
                    $data['branch_id'] = $staff->assigned_branch_id;
                } else {
                    // If selected staff doesn't have an assigned branch, show error
                    \Filament\Notifications\Notification::make()
                        ->title('Cannot Create Leave Request')
                        ->body('The selected staff member must have an assigned branch. Please assign a branch to this staff member first.')
                        ->danger()
                        ->send();
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        ['staff_id' => ['The selected staff member must have an assigned branch.']]
                    );
                }
            }
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
