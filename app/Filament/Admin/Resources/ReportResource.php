<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReportResource\Pages;
use App\Filament\Admin\Resources\ReportResource\RelationManagers;
use App\Models\Report;
use App\Models\ReportTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

class ReportResource extends Resource
{

    protected static ?string $model = ReportTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationGroup = 'Reports';

    public static function getAvailableModels(): array
    {
        return [
            'PurchaseRequests' => 'Purchase Requests',
            'PurchaseOrders' => 'Purchase Orders',
            'PettyCashReimbursment' => 'Petty Cash',
            'SubBudgetAccounts' => 'Sub Budget Accounts',
            'BudgetTransactionHistory' => 'Budget Transactions',
            // Add other models as needed
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
    
                        Forms\Components\Hidden::make('created_by')
                            ->default(auth()->id())
                            ->required(),
                        
                        Forms\Components\Select::make('model_type')
                            ->label('Select Model')
                            ->options(self::getAvailableModels())
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => 
                                $set('selected_fields', [])),
    
                        Forms\Components\Repeater::make('field_configs')
                            ->label('Select Fields with Filters')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->label('Field')
                                    ->options(function (callable $get) {
                                        $modelType = $get('../../model_type');
                                        if (!$modelType) return [];
                                        return collect(self::getFieldsWithRelations($modelType))
                                            ->mapWithKeys(fn ($config, $field) => [
                                                $field => $config['label']
                                            ]);
                                    })
                                    ->reactive()
                                    ->required(),
    
                                Forms\Components\Select::make('filter_type')
                                    ->label('Filter Type')
                                    ->options([
                                        'equals' => 'Equals',
                                        'contains' => 'Contains',
                                        'starts_with' => 'Starts With',
                                        'ends_with' => 'Ends With',
                                        'greater_than' => 'Greater Than',
                                        'less_than' => 'Less Than',
                                        'between' => 'Between',
                                        'in' => 'In List',
                                    ])
                                    ->visible(fn (callable $get) => filled($get('field')))
                                    ->reactive(),
    
                                Forms\Components\TextInput::make('filter_value')
                                    ->label('Filter Value')
                                    ->visible(fn (callable $get) => 
                                        filled($get('field')) && 
                                        !in_array($get('filter_type'), ['between', 'in'])),
    
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('filter_value_from')
                                            ->label('From'),
                                        Forms\Components\TextInput::make('filter_value_to')
                                            ->label('To'),
                                    ])
                                    ->visible(fn (callable $get) => 
                                        $get('filter_type') === 'between'),
    
                                Forms\Components\TagsInput::make('filter_values')
                                    ->label('Values')
                                    ->visible(fn (callable $get) => 
                                        $get('filter_type') === 'in'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
    
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('from_date')
                                    ->label('From Date')
                                    ->native(false)
                                    ->closeOnDateSelection(),
                                Forms\Components\DatePicker::make('to_date')
                                    ->label('To Date')
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->after('from_date'),
                            ]),
    
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')
                ->searchable(),
            Tables\Columns\TextColumn::make('model_type')
                ->label('Report Type'),
            Tables\Columns\TextColumn::make('creator.name')
                ->label('Created By'),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ])
        ->filters([
            //
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
            Tables\Actions\Action::make('generate')
                ->label('Generate Report')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (ReportTemplate $record) {
                    return static::generateReport($record);
                }),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);

    }

    public static function getFieldsWithRelations(string $modelType): array
    {
        $modelClass = "App\\Models\\{$modelType}";
        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass;
        $fields = collect(Schema::getColumnListing($model->getTable()))
            ->mapWithKeys(fn ($field) => [
                $field => [
                    'label' => ucwords(str_replace('_', ' ', $field)),
                    'type' => 'column',
                    'relation' => null
                ]
            ]);

        // Add relationship fields
        foreach ($model->getRelations() as $relation => $type) {
            $relationModel = $model->{$relation}()->getRelated();
            $relationFields = Schema::getColumnListing($relationModel->getTable());
            
            foreach ($relationFields as $field) {
                $fields->put("{$relation}.{$field}", [
                    'label' => ucwords(str_replace('_', ' ', $relation)) . ' ' . ucwords(str_replace('_', ' ', $field)),
                    'type' => 'relation',
                    'relation' => $relation
                ]);
            }
        }

        return $fields->toArray();
    }

    protected static function generateReport(ReportTemplate $template)
    {
        $modelClass = "App\\Models\\{$template->model_type}";
        $query = $modelClass::query();
    
        // Handle relations
        $relations = collect($template->field_configs)
            ->pluck('field')
            ->filter(fn ($field) => str_contains($field, '.'))
            ->map(fn ($field) => explode('.', $field)[0])
            ->unique()
            ->toArray();
    
        foreach ($relations as $relation) {
            $query->with($relation);
        }
    
        // Apply field filters
        foreach ($template->field_configs as $config) {
            if (empty($config['filter_type'])) continue;
    
            $field = $config['field'];
            $filterType = $config['filter_type'];
            $value = $config['filter_value'] ?? null;
    
            switch ($filterType) {
                case 'equals':
                    $query->where($field, $value);
                    break;
                case 'contains':
                    $query->where($field, 'like', "%{$value}%");
                    break;
                case 'starts_with':
                    $query->where($field, 'like', "{$value}%");
                    break;
                case 'ends_with':
                    $query->where($field, 'like', "%{$value}");
                    break;
                case 'greater_than':
                    $query->where($field, '>', $value);
                    break;
                case 'less_than':
                    $query->where($field, '<', $value);
                    break;
                case 'between':
                    $query->whereBetween($field, [
                        $config['filter_value_from'],
                        $config['filter_value_to']
                    ]);
                    break;
                case 'in':
                    $query->whereIn($field, $config['filter_values']);
                    break;
            }
        }
    
        // Apply date range filter
        if (!empty($template->from_date) && !empty($template->to_date)) {
            $query->whereBetween('created_at', [
                $template->from_date . ' 00:00:00',
                $template->to_date . ' 23:59:59'
            ]);
        }
    
        // Get selected fields
        $selectedFields = collect($template->field_configs)->pluck('field')->toArray();
        $records = $query->get($selectedFields);
    
        // Generate CSV
        $csv = \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());
        
        // Add headers with proper labels
        $headers = collect($template->field_configs)
            ->pluck('field')
            ->map(fn($field) => ucwords(str_replace('_', ' ', $field)))
            ->toArray();
        $csv->insertOne($headers);
        
        // Add data rows
        foreach ($records as $record) {
            $row = [];
            foreach ($selectedFields as $field) {
                if (str_contains($field, '.')) {
                    // Handle relation fields
                    $parts = explode('.', $field);
                    $value = $record;
                    foreach ($parts as $part) {
                        $value = $value->{$part} ?? null;
                    }
                    $row[] = self::formatValue($value);
                } else {
                    $row[] = self::formatValue($record->{$field});
                }
            }
            $csv->insertOne($row);
        }
    
        // Return downloadable response
        return response()->streamDownload(
            function () use ($csv) {
                echo $csv->toString();
            },
            "{$template->name}-" . now()->format('Y-m-d') . '.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    protected static function formatValue($value): string
    {
        if (is_null($value)) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : get_class($value);
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
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
            'index' => Pages\ListReports::route('/'),
            'create' => Pages\CreateReport::route('/create'),
            'edit' => Pages\EditReport::route('/{record}/edit'),
        ];
    }
}
