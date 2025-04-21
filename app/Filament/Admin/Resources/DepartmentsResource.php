<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DepartmentsResource\Pages;
use App\Filament\Admin\Resources\DepartmentsResource\RelationManagers\HodfromusersRelationManager;
use App\Models\Departments;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\AssociateAction;
use Filament\Tables\Table;

class DepartmentsResource extends Resource
{
    protected static ?string $model = Departments::class;

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('petty_cash_float_amount')
                    ->required()
                    ->numeric(),
                // Forms\Components\TextInput::make('hod')
                //     ->label('Head of Department')
                //     ->helperText('Full name of the Head of Department')
                //     ->required()
                //     ->maxLength(255),
                Forms\Components\TextInput::make('hod_of')
                ->label('Assigned Heads of Department')
                ->helperText('Please Assign HODs from User Settings')
                ->placeholder(function (Departments $department): ?string {
                    $department->load('hodfromusers'); // Load the related HODs
                    if ($department->hodfromusers->isNotEmpty()) {
                        // Join all HOD names into a comma-separated string
                        return $department->hodfromusers->pluck('name')->join(', ');
                    }
                    return 'No HODs assigned';
                })
                ->disabled(),
                // Forms\Components\TextInput::make('hod_designation')
                //     ->label('HOD Designation')
                //     ->helperText('Designation of the Head of Department')
                //     ->required()
                //     ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hodfromusers.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartments::route('/create'),
            'edit' => Pages\EditDepartments::route('/{record}/edit'),
        ];
    }
}
