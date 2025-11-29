<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResidentSignOutResource\Pages;
use App\Models\ResidentSignOut;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResidentSignOutResource extends Resource
{
    protected static ?string $model = ResidentSignOut::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';
    protected static ?string $navigationLabel = 'Resident Sign-Outs';
    protected static ?string $modelLabel = 'Resident Sign-Out';
    protected static ?string $pluralModelLabel = 'Resident Sign-Outs';
    protected static ?string $navigationGroup = 'Resident Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sign-Out Details')
                    ->schema([
                        Forms\Components\Select::make('resident_id')
                            ->label('Resident')
                            ->relationship('resident', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('destination')
                            ->label('Destination')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('purpose')
                            ->label('Purpose')
                            ->rows(3)
                            ->maxLength(1000),
                        Forms\Components\TextInput::make('accompanied_by')
                            ->label('Accompanied By')
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('expected_return_at')
                            ->label('Expected Return')
                            ->native(false),
                        Forms\Components\Toggle::make('emergency_contact_notified')
                            ->label('Emergency Contact Notified'),
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
                Tables\Columns\TextColumn::make('resident.name')
                    ->label('Resident')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('destination')
                    ->label('Destination')
                    ->limit(30),
                Tables\Columns\TextColumn::make('sign_out_at')
                    ->label('Signed Out')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expected_return_at')
                    ->label('Expected Return')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('sign_in_at')
                    ->label('Signed In')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->colors([
                        'warning' => true,
                        'success' => false,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Out' : 'Returned'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Out',
                        false => 'Returned',
                    ]),
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
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
            ->defaultSort('sign_out_at', 'desc');
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
            'index' => Pages\ListResidentSignOuts::route('/'),
            'create' => Pages\CreateResidentSignOut::route('/create'),
            'view' => Pages\ViewResidentSignOut::route('/{record}'),
            'edit' => Pages\EditResidentSignOut::route('/{record}/edit'),
        ];
    }
}

