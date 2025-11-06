<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Activity Logs';
    protected static ?string $modelLabel = 'Activity Log';
    protected static ?string $pluralModelLabel = 'Activity Logs';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 100;

    public static function canCreate(): bool
    {
        return false; // Logs are created automatically, not manually
    }

    public static function canEdit($record): bool
    {
        return false; // Logs are read-only
    }

    public static function canDelete($record): bool
    {
        // Only allow admins to delete logs
        return auth()->user()->hasPermission('delete_activity_logs') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermission('view_activity_logs') ?? auth()->user()->hasRole('administrator');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->latest('logged_at');
        
        // If user is a caregiver, show logs for their branch only
        if (auth()->user()->hasRole('caregiver')) {
            $query->where('branch_id', auth()->user()->assigned_branch_id);
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Log Information')
                    ->schema([
                        Forms\Components\TextInput::make('log_type')
                            ->label('Log Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('event')
                            ->label('Event')
                            ->disabled(),
                        Forms\Components\TextInput::make('level')
                            ->label('Level')
                            ->disabled(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->disabled(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Subject Information')
                    ->schema([
                        Forms\Components\TextInput::make('subject_type')
                            ->label('Subject Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('subject_id')
                            ->label('Subject ID')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('User & Context')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->disabled(),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->relationship('branch', 'name')
                            ->disabled(),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),
                        Forms\Components\Textarea::make('user_agent')
                            ->label('User Agent')
                            ->rows(2)
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('logged_at')
                            ->label('Logged At')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Properties')
                    ->schema([
                        Forms\Components\KeyValue::make('properties')
                            ->label('Properties')
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Context')
                    ->schema([
                        Forms\Components\KeyValue::make('context')
                            ->label('Context')
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('logged_at')
                    ->label('Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->default('System'),
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'viewed' => 'gray',
                        'login' => 'success',
                        'logout' => 'gray',
                        default => 'primary',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('log_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'activity' => 'primary',
                        'audit' => 'warning',
                        'error' => 'danger',
                        'system' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (Tables\Columns\TextColumn $column): ?string => 
                        strlen($column->getState() ?? '') > 50 ? $column->getState() : null
                    ),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A')
                    ->searchable(),
                Tables\Columns\TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'debug' => 'gray',
                        'info' => 'info',
                        'warning' => 'warning',
                        'error' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_type')
                    ->label('Log Type')
                    ->options(ActivityLog::getLogTypeOptions()),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Event')
                    ->options(ActivityLog::getEventOptions()),
                Tables\Filters\SelectFilter::make('level')
                    ->label('Level')
                    ->options(ActivityLog::getLevelOptions()),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('logged_at')
                    ->form([
                        Forms\Components\DatePicker::make('logged_from')
                            ->label('Logged From'),
                        Forms\Components\DatePicker::make('logged_until')
                            ->label('Logged Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['logged_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('logged_at', '>=', $date),
                            )
                            ->when(
                                $data['logged_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('logged_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('subject_type')
                    ->form([
                        Forms\Components\TextInput::make('subject_type')
                            ->label('Subject Type (Model)')
                            ->placeholder('e.g., App\\Models\\Resident'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['subject_type'],
                            fn (Builder $query, $type): Builder => $query->where('subject_type', $type)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasPermission('delete_activity_logs') ?? false),
                ]),
            ])
            ->defaultSort('logged_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }
}
