<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReportResource\Pages;
use App\Models\ReportTemplate;
use App\Services\ReportGenerator;
use App\Support\ReportFieldCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = ReportTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Report Templates';

    protected static ?string $modelLabel = 'report template';

    protected static ?string $pluralModelLabel = 'report templates';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report details')
                    ->description('Name the template and choose which data source to export.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('model_type')
                            ->label('Data source')
                            ->options(ReportFieldCatalog::MODELS)
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('field_configs', [])),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Fields & filters')
                    ->description('Add columns to export. Filters work on direct table columns only.')
                    ->schema([
                        Forms\Components\Repeater::make('field_configs')
                            ->label('Columns')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->label('Column')
                                    ->options(fn (Get $get): array => collect(
                                        ReportFieldCatalog::fieldsFor($get('../../model_type') ?? '')
                                    )->mapWithKeys(fn (array $config, string $field) => [
                                        $field => $config['label'],
                                    ])->all())
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('filter_type', null);
                                        $set('filter_value', null);
                                        $set('filter_value_from', null);
                                        $set('filter_value_to', null);
                                        $set('filter_values', []);
                                    })
                                    ->columnSpan(2),

                                Forms\Components\Select::make('filter_type')
                                    ->label('Filter')
                                    ->options(fn (Get $get): array => ReportFieldCatalog::filterTypesForField(
                                        $get('../../model_type') ?? '',
                                        $get('field'),
                                    ))
                                    ->placeholder('No filter')
                                    ->native(false)
                                    ->live()
                                    ->visible(fn (Get $get): bool => filled($get('field')) && ! str_contains((string) $get('field'), '.'))
                                    ->columnSpan(1),

                                Forms\Components\Select::make('filter_value')
                                    ->label('Value')
                                    ->options(fn (Get $get): array => ReportFieldCatalog::statusOptions(
                                        $get('../../model_type') ?? '',
                                        $get('field'),
                                    ))
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn (Get $get): bool => filled($get('filter_type'))
                                        && ! in_array($get('filter_type'), ['between', 'in'], true)
                                        && ReportFieldCatalog::isStatusField($get('../../model_type') ?? '', $get('field')))
                                    ->dehydrated(fn (Get $get): bool => ReportFieldCatalog::isStatusField(
                                        $get('../../model_type') ?? '',
                                        $get('field'),
                                    ))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('filter_value')
                                    ->label('Value')
                                    ->visible(fn (Get $get): bool => filled($get('filter_type'))
                                        && ! in_array($get('filter_type'), ['between', 'in'], true)
                                        && ! ReportFieldCatalog::isStatusField($get('../../model_type') ?? '', $get('field')))
                                    ->dehydrated(fn (Get $get): bool => ! ReportFieldCatalog::isStatusField(
                                        $get('../../model_type') ?? '',
                                        $get('field'),
                                    ))
                                    ->columnSpan(1),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('filter_value_from')
                                            ->label('From'),
                                        Forms\Components\TextInput::make('filter_value_to')
                                            ->label('To'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('filter_type') === 'between'
                                        && ! ReportFieldCatalog::isStatusField($get('../../model_type') ?? '', $get('field')))
                                    ->columnSpan(2),

                                Forms\Components\Select::make('filter_values')
                                    ->label('Values')
                                    ->options(fn (Get $get): array => ReportFieldCatalog::statusOptions(
                                        $get('../../model_type') ?? '',
                                        $get('field'),
                                    ))
                                    ->multiple()
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn (Get $get): bool => $get('filter_type') === 'in'
                                        && ReportFieldCatalog::isStatusField($get('../../model_type') ?? '', $get('field')))
                                    ->dehydrated(fn (Get $get): bool => ReportFieldCatalog::isStatusField(
                                        $get('../../model_type') ?? '',
                                        $get('field'),
                                    ))
                                    ->columnSpan(2),

                                Forms\Components\TagsInput::make('filter_values')
                                    ->label('Values')
                                    ->visible(fn (Get $get): bool => $get('filter_type') === 'in'
                                        && ! ReportFieldCatalog::isStatusField($get('../../model_type') ?? '', $get('field')))
                                    ->dehydrated(fn (Get $get): bool => ! ReportFieldCatalog::isStatusField(
                                        $get('../../model_type') ?? '',
                                        $get('field'),
                                    ))
                                    ->columnSpan(2),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Add column')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['field'] ?? null)
                                ? str($state['field'])->replace('.', ' · ')->replace('_', ' ')->title()->toString()
                                : 'New column')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Date range')
                    ->description('Optional. Limits rows by created date on the selected data source.')
                    ->schema([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From')
                            ->native(false)
                            ->closeOnDateSelection(),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('To')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->after('from_date'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ReportTemplate $record): ?string => $record->description),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Data source')
                    ->formatStateUsing(fn (string $state): string => ReportFieldCatalog::modelLabel($state))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('field_configs')
                    ->label('Columns')
                    ->formatStateUsing(function (mixed $state): string {
                        if (is_string($state)) {
                            $state = json_decode($state, true);
                        }

                        return (string) count(is_array($state) ? $state : []);
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created by')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Data source')
                    ->options(ReportFieldCatalog::MODELS),
            ])
            ->actions([
                Tables\Actions\Action::make('generate')
                    ->label('Download CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn (ReportTemplate $record) => app(ReportGenerator::class)->download($record)),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No report templates yet')
            ->emptyStateDescription('Create a template to export filtered data as CSV.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'create' => Pages\CreateReport::route('/create'),
            'edit' => Pages\EditReport::route('/{record}/edit'),
        ];
    }
}
