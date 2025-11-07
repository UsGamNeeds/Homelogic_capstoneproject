<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ResidentResource;
use App\Models\Resident;
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

class InactiveResidents extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Inactive Residents';

    protected static ?string $title = 'Inactive Residents';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.inactive-residents';

    protected static string $routePath = 'inactive-residents';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('view_residents');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Resident Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room')
                    ->label('Room')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('diagnosis')
                    ->label('Diagnosis')
                    ->limit(30)
                    ->tooltip(fn (TextColumn $column) => strlen($column->getState() ?? '') > 30 ? $column->getState() : null),
                TextColumn::make('admission_date')
                    ->label('Admitted')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('emergency_contact_name')
                    ->label('Emergency Contact')
                    ->limit(25)
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger')
                    ->trueColor('success'),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Resident $record) => ResidentResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
                Action::make('activate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Resident $record): void {
                        $record->update(['is_active' => true]);

                        Notification::make()
                            ->title('Resident reactivated successfully')
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
        return Resident::query()
            ->with('branch')
            ->where('is_active', false)
            ->orderBy('name');
    }
}
