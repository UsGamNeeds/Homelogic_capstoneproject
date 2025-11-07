<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InactiveUsers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';

    protected static ?string $navigationLabel = 'Inactive Users';

    protected static ?string $title = 'Inactive Users';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.inactive-users';

    protected static string $routePath = 'inactive-users';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('view_users');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('User Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                TextColumn::make('assignedBranch.name')
                    ->label('Assigned Branch')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->size('sm'),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->copyable()
                    ->copyMessage('Phone copied')
                    ->size('sm'),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger')
                    ->trueColor('success'),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(User::getRoleOptions()),
                SelectFilter::make('assigned_branch_id')
                    ->label('Branch')
                    ->relationship('assignedBranch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (User $record) => UserResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                Action::make('activate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update(['is_active' => true]);

                        Notification::make()
                            ->title('User reactivated successfully')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->defaultSort('name');
    }

    protected function getTableQuery(): Builder
    {
        return User::query()
            ->with('assignedBranch')
            ->where('is_active', false)
            ->orderBy('name');
    }
}
