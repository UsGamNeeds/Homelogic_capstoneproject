<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit User')
                ->color('primary')
                ->icon('heroicon-o-pencil'),
            // Hidden for anyone with care records behind them: deleting a user
            // cascade-deletes the doses they signed and the incidents they
            // reported. Deactivate those users from the staff list instead.
            Actions\DeleteAction::make()
                ->label('Delete User')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->visible(fn () => ! $this->getRecord()->hasProtectedRecords()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Personal Information')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label('Full Name')
                                        ->weight('bold')
                                        ->size('lg'),
                                    Infolists\Components\TextEntry::make('email')
                                        ->label('Email')
                                        ->copyable()
                                        ->copyMessage('Email copied')
                                        ->icon('heroicon-o-envelope'),
                                ]),
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('phone_number')
                                    ->label('Phone Number')
                                    ->copyable()
                                    ->copyMessage('Phone copied')
                                    ->icon('heroicon-o-phone'),
                                Infolists\Components\TextEntry::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->date('M j, Y')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                        ]),
                        Infolists\Components\Split::make([
                            Infolists\Components\TextEntry::make('marital_status')
                                ->label('Marital Status')
                                ->badge()
                                ->color('secondary'),
                            Infolists\Components\TextEntry::make('sex')
                                ->label('Sex')
                                ->badge()
                                ->color('info'),
                        ]),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Employment Details')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('position')
                                        ->label('Position')
                                        ->badge()
                                        ->color('primary'),
                                    Infolists\Components\TextEntry::make('role')
                                        ->label('Role')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'administrator' => 'danger',
                                            'manager' => 'warning',
                                            'registered_nurse' => 'info',
                                            'care_giver' => 'success',
                                            'superuser' => 'primary',
                                            default => 'gray',
                                        }),
                                ]),
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('date_employed')
                                    ->label('Date Employed')
                                    ->date('M j, Y')
                                    ->icon('heroicon-o-calendar'),
                                Infolists\Components\TextEntry::make('is_active')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                            ]),
                        ]),
                        Infolists\Components\Split::make([
                            Infolists\Components\TextEntry::make('credentials')
                                ->label('Credentials')
                                ->badge()
                                ->color('secondary'),
                            Infolists\Components\TextEntry::make('credential_details')
                                ->label('Credential Details')
                                ->placeholder('No details provided'),
                        ]),
                        Infolists\Components\Split::make([
                            Infolists\Components\TextEntry::make('supervisor_name')
                                ->label('Supervisor')
                                ->placeholder('No supervisor assigned'),
                            Infolists\Components\TextEntry::make('provider_name')
                                ->label('Provider')
                                ->placeholder('No provider assigned'),
                        ]),
                        Infolists\Components\TextEntry::make('assignedBranch.name')
                            ->label('Assigned Branch')
                            ->badge()
                            ->color('warning')
                            ->placeholder('No branch assigned'),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes available')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Account Created')
                            ->dateTime('M j, Y g:i A')
                            ->icon('heroicon-o-calendar'),
                    ])
                    ->columns(1),
            ]);
    }
}
