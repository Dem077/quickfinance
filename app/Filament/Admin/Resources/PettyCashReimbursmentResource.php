<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PettyCashStatus;
use App\Filament\Admin\Resources\PettyCashReimbursmentResource\Pages;
use App\Filament\Admin\Resources\PettyCashReimbursmentResource\RelationManagers\PettyCashReimbursmentDetailRelationManager;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseOrders;
use App\Models\SubBudgetAccounts;
use App\Models\Vendors;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class PettyCashReimbursmentResource extends Resource
{
    protected static ?string $model = PettyCashReimbursment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Hidden::make('user_id')
                    ->default(Auth::id())
                    ->required(),
                Forms\Components\FileUpload::make('supporting_documents')
                    ->nullable()
                    ->helperText('Any additional upload supporting documents.')
                    ->downloadable(),
                Forms\Components\Hidden::make('status')
                    ->default(PettyCashStatus::Draft)
                    ->required(),
                Section::make('Details')
                    ->schema([
                        Forms\Components\Repeater::make('reimbursementsitems')
                            ->label('Items / Services')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(12)
                                    ->schema([
                                        Forms\Components\DatePicker::make('date')
                                            ->native(false)
                                            ->closeOnDateSelection()
                                            ->required()
                                            ->columnSpan(1),
                                        Forms\Components\Select::make('Vendor_id')
                                            ->options(
                                                Vendors::all()->pluck('name', 'id')
                                            )
                                            ->native(false)
                                            ->columnSpan(2)
                                            ->required(),
                                        Forms\Components\TextInput::make('bill_no')
                                            ->columnSpan(2)
                                            ->required(),
                                        Forms\Components\TextInput::make('details')
                                            ->columnSpan(2)
                                            ->required(),
                                        Forms\Components\Select::make('sub_budget_id')
                                            ->options(
                                                SubBudgetAccounts::all()->pluck('name', 'id')
                                            )
                                            ->native(false)
                                            ->columnSpan(2)
                                            ->nullable(),
                                        Forms\Components\Select::make('po_id')
                                            ->options(
                                                PurchaseOrders::where('is_closed', true)
                                                    ->where('payment_method', 'petty_cash')
                                                    ->pluck('po_no', 'id')
                                            )
                                            ->native(false)
                                            ->columnSpan(2)
                                            ->nullable(),
                                        
                                        Forms\Components\TextInput::make('amount')
                                            ->columnSpan(1)
                                            ->minValue(1)
                                            ->required()
                                            ->numeric(),
                                        ]),
                        ]),    
                    ])->hidden(fn (string $operation): bool => $operation === 'edit'),
                        
            ]);
            
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Reqeust ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('MVR', locale: 'us')
                    ->getStateUsing(fn ($record) => $record->pettyCashReimbursmentDetails->sum('amount'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->getStateUsing(fn ($record) =>
                        $record->status->value === PettyCashStatus::Submitted->value ? 'Submitted' : 
                        ($record->status->value === PettyCashStatus::DepApproved->value ? 'Department HOD Approved' : 
                        ($record->status->value === PettyCashStatus::Dep_Reject->value ? 'Department HOD Rejected' : 
                        ($record->status->value === PettyCashStatus::FinApproved->value ? 'Finance Approved' : 
                        ($record->status->value === PettyCashStatus::Fin_Reject->value ? 'Finance Rejected' : 
                        ($record->status->value === PettyCashStatus::Rembursed->value ? 'Rembursed' : 'Draft'))))))
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Department HOD Approved' => 'success',
                        'Finance Approved' => 'success',
                        'Rembursed' => 'success',
                        'Finance Rejected' => 'danger',
                        'Department HOD Rejected' => 'danger',
                        'Submitted' => 'warning',
                        'Draft' => 'gray',
                        default => 'primary',
                    }),
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
                
                Tables\Actions\Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::Draft->value)
                    ->action(fn ($record) => $record->update(['status' => PettyCashStatus::Submitted])),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::Submitted->value && !Auth::user()->hod_of==null && $record->user->department_id==Auth::user()->hod_of)
                    ->action(fn ($record) => $record->update(['status' => PettyCashStatus::DepApproved])),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::Draft->value),
            ])
            ->recordUrl(false)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PettyCashReimbursmentDetailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPettyCashReimbursments::route('/'),
            'create' => Pages\CreatePettyCashReimbursment::route('/create'),
            'edit' => Pages\EditPettyCashReimbursment::route('/{record}/edit'),
        ];
    }
}
