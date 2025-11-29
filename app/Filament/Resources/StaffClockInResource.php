<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffClockInResource\Pages;
use App\Models\StaffClockIn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StaffClockInResource extends Resource
{
    protected static ?string $model = StaffClockIn::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Staff Clock-Ins';
    protected static ?string $modelLabel = 'Clock-In';
    protected static ?string $pluralModelLabel = 'Clock-Ins';
    protected static ?string $navigationGroup = 'Staff Management';

    public static function canViewAny(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        
        $user = Auth::user();
        
        // Allow admins and super admins
        return $user->hasRole('administrator') || 
               $user->hasRole('super_admin') || 
               $user->role === 'administrator' || 
               $user->role === 'super_admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Clock-In Details')
                    ->schema([
                        Forms\Components\Select::make('staff_id')
                            ->label('Staff Member')
                            ->relationship('staff', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\DateTimePicker::make('clock_in_at')
                            ->label('Clock In Time')
                            ->required()
                            ->native(false),
                        Forms\Components\DateTimePicker::make('clock_out_at')
                            ->label('Clock Out Time')
                            ->native(false),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('clock_in_at')
                    ->label('Clock In')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('clock_out_at')
                    ->label('Clock Out')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('total_hours')
                    ->label('Hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs'),
                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Completed'),
                Tables\Columns\BadgeColumn::make('clock_method')
                    ->label('Method')
                    ->colors([
                        'primary' => 'authenticated',
                        'warning' => 'public',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('staff_id')
                    ->label('Staff Member')
                    ->relationship('staff', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('clock_method')
                    ->label('Method')
                    ->options([
                        'authenticated' => 'Authenticated',
                        'public' => 'Public',
                    ]),
                Tables\Filters\Filter::make('clock_in_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('clock_in_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('clock_in_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('clock_in_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // Apply facility filtering for non-super admins
        if ($user && $user->role !== 'super_admin' && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffClockIns::route('/'),
            'create' => Pages\CreateStaffClockIn::route('/create'),
            'view' => Pages\ViewStaffClockIn::route('/{record}'),
            'edit' => Pages\EditStaffClockIn::route('/{record}/edit'),
        ];
    }
}

