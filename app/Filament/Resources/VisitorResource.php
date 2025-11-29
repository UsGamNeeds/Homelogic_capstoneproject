<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorResource\Pages;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Visitors';
    protected static ?string $modelLabel = 'Visitor';
    protected static ?string $pluralModelLabel = 'Visitors';
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Visitor Information')
                    ->schema([
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->relationship('branch', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('visit_purpose')
                            ->label('Visit Purpose')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('visiting_resident_id')
                            ->label('Visiting Resident')
                            ->relationship('visitingResident', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('visiting_staff_id')
                            ->label('Visiting Staff')
                            ->relationship('visitingStaff', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('expected_duration_minutes')
                            ->label('Expected Duration (minutes)')
                            ->numeric(),
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
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Visitor')
                    ->getStateUsing(fn ($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('visit_purpose')
                    ->label('Purpose')
                    ->limit(30),
                Tables\Columns\TextColumn::make('visitingResident.name')
                    ->label('Visiting')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('check_in_at')
                    ->label('Check In')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out_at')
                    ->label('Check Out')
                    ->dateTime('m/d/Y g:i A')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->colors([
                        'warning' => true,
                        'success' => false,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Checked In' : 'Checked Out'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Checked In',
                        false => 'Checked Out',
                    ]),
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
            ->defaultSort('check_in_at', 'desc');
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
            'index' => Pages\ListVisitors::route('/'),
            'create' => Pages\CreateVisitor::route('/create'),
            'view' => Pages\ViewVisitor::route('/{record}'),
            'edit' => Pages\EditVisitor::route('/{record}/edit'),
        ];
    }
}

