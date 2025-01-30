<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;
use App\Filament\Admin\Resources\PurchaseRequestsResource\RelationManagers;
use App\Models\PurchaseRequests;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PurchaseRequestsResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = PurchaseRequests::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'send_approval',
            'approve'
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        if(Auth::user()->can('approve_purchase::requests')){
            return parent::getEloquentQuery()->where('is_submited', true);
        }else if(Auth::user()->can('send_approval_purchase::requests')){
            return parent::getEloquentQuery()->where('department_id', Auth::user()->department_id || parent::getEloquentQuery()->where('user_id', Auth::id()));
        }else {
            return parent::getEloquentQuery();
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('pr_no')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->disabled()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests'))
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('department_id')
                    ->relationship('department', 'name')
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests'))
                    ->required(),
                Forms\Components\Select::make('budget_account_id')
                    ->relationship('budgetAccount', 'name', function ($query) {
                        $query->selectRaw("CONCAT(code, ' - ', budget_accounts.name, ' (', budget_accounts.expenditure_type, ' - ', budget_accounts.account, ')') AS display_name, budget_accounts.id")
                            ->join('budget_accounts', 'sub_budget_accounts.budget_account_id', '=', 'budget_accounts.id');
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable(['budget_accounts.name', 'code', 'budget_accounts.expenditure_type', 'budget_accounts.account'])
                    ->preload()
                    ->required(),
                Forms\Components\Hidden::make('user_id')
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests'))
                    ->default(fn () => Auth::id())
                    ->required(),
                Section::make('Items')
                    ->schema([
                        Forms\Components\Repeater::make('purchaseOrderDetails')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(7)
                                    ->schema([
                                        Forms\Components\TextInput::make('item')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('unit')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),
                                        Forms\Components\TextInput::make('amount')
                                            ->required()
                                            ->numeric()
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minItems(1),
                    ])->hidden(fn (string $operation): bool => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pr_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('budget_account_id')
                    ->label('Budget Code')
                    ->getStateUsing(fn ($record) => $record->budgetAccount->code ?? '-')
                    ->searchable(['budget_accounts.name', 'budget_accounts.code', 'budget_accounts.expenditure_type', 'budget_accounts.account']),
                Tables\Columns\TextColumn::make('department.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->numeric()
                    ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                    ->label('Status') // Add a label for better readability
                    ->getStateUsing(fn ($record) => 
                        $record->is_approved ? 'Approved' : 
                        ($record->is_canceled ? 'Canceled' : 
                        ($record->is_submited ? 'Submitted' : 'Draft'))
                    )
                    ->sortable() // Allow sorting by this column
                    ->searchable() // Optional: Allow searching by this column
                    ->badge() // Optional: Display the status as a badge for better UI
                    ->color(fn ($state) => match ($state) {
                        'Approved' => 'success',
                        'Canceled' => 'danger',
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

            ])
            ->actions([
                Tables\Actions\Action::make('submit_for_approval')
                    ->label('Submit for Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => 
                        !$record->is_submited && 
                        Auth::user()->can('send_approval_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record) {
                        $record->update([
                            'is_submited' => true,
                        ]);
                        Notification::make()
                            ->title('Submitted for approval successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('approve_purchase_request')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => 
                        $record->is_submited &&
                        !$record->is_canceled && 
                        !$record->is_approved && 
                        Auth::user()->can('approve_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record) {
                        $record->update([
                            'is_approved' => true,
                        ]);
                        Notification::make()
                            ->title('PR Approved successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('cancel_purchase_request')
                    ->label('Cancel')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('cancel_remark')
                            ->label('Cancellation Reason')
                            ->required()
                            ->maxLength(255)
                    ])
                    ->visible(fn ($record) => 
                        $record->is_submited &&
                        !$record->is_approved && 
                        !$record->is_canceled && 
                        Auth::user()->can('approve_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record ,array $data) {
                        $record->update([
                            'is_canceled' => true,
                            'cancel_remark' => $data['cancel_remark']
                        ]);
                        Notification::make()
                            ->title('PR Approved successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => (!$record->is_canceled && $record->is_submited && !$record->is_approved && Auth::user()->can('approve_purchase::requests') )|| (!$record->is_submited &&  Auth::user()->can('send_approval_purchase::requests'))),
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
            RelationManagers\PurchaseRequestDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseRequests::route('/'),
            'create' => Pages\CreatePurchaseRequests::route('/create'),
            'edit' => Pages\EditPurchaseRequests::route('/{record}/edit'),
        ];
    }


}
