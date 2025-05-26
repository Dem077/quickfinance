<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;
use App\Filament\Admin\Resources\PurchaseOrdersResource\RelationManagers;
use App\Models\AdvanceForm;
use App\Models\BudgetTransactionHistory;
use App\Models\Item;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PurchaseOrdersResource extends Resource
{
    protected static ?string $model = PurchaseOrders::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Procure';

    protected ?string $heading = 'Procure';

    protected static ?string $navigationLabel = 'Procure';

    protected static ?string $slug = 'procure';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'generate_advance_form',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('po_no')
                    ->label('Record ID')
                    ->required()
                    ->disabled(fn ($record) => $record && $record->status !== 'draft')
                    ->maxLength(255),
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->disabled(fn ($record) => $record && $record->status !== 'draft')
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->disabled(fn ($record) => $record && $record->status !== 'draft')
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('pr_id')
                    ->relationship('purchaseRequest', 'pr_no', function ($query) {
                        return $query->whereNotNull('uploaded_document')->wherenot('status', PurchaseRequestsStatus::Closed->value);
                    })
                    ->preload()
                    ->live()
                    ->disabled(fn ($record) => $record && $record->status !== 'draft')
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $set('purchaseRequestDetails.*.pr_id', $state);
                    })
                    ->required(),
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->live()
                    ->disabled(fn ($record) => $record && $record->status !== 'draft')
                    ->native(false)
                    ->options([
                        'purchase_order' => 'Purchase Order',
                        'petty_cash' => 'Petty Cash',
                    ])
                    ->default('purchase_order')
                    ->required(),
                Forms\Components\Radio::make('is_advance_form_required')
                    ->label('Advance Form Required')
                    ->inline()
                    ->disabled(fn ($record) => $record && $record->status !== 'draft')
                    ->inlineLabel(false)
                    ->default('0')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->visible(fn (Get $get) => $get('payment_method') === 'purchase_order')
                    ->required(),
                Section::make('Item to be Utilized')
                    ->schema([
                        Forms\Components\Repeater::make('purchaseOrderDetails')
                            ->label('Items / Services')
                            ->extraItemActions([
                                Forms\Components\Actions\Action::make('calculate_all')
                                    ->label('Generate Total')
                                    ->icon('heroicon-m-calculator')
                                    ->color('info')
                                    ->tooltip('Generate Total Amount')
                                    ->action(function (Get $get, Set $set) {
                                        $items = $get('purchaseOrderDetails');
                                        if (! is_array($items)) {
                                            return;
                                        }

                                        foreach ($items as $key => $item) {
                                            $qty = (float) ($item['qty'] ?? 0);
                                            $gst = (float) ($item['gst'] ?? 0);
                                            $unitPrice = (float) ($item['unit_price'] ?? 0);
                                            $set("purchaseOrderDetails.{$key}.amount", $qty * $unitPrice + ($qty * $unitPrice * ($gst / 100)));
                                        }
                                    }),
                            ])
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(14)
                                    ->schema([
                                        Forms\Components\TextInput::make('itemcode')
                                            ->label('Item Code')
                                            ->required()
                                            ->disabled()
                                            ->live()
                                            ->dehydrated(false)
                                            ->columnSpan(2),
                                        Forms\Components\Hidden::make('budget_account')
                                            ->label('Budget Code')
                                            ->required()
                                            ->disabled()
                                            ->live()
                                            ->dehydrated(false),
                                        Forms\Components\Select::make('desc')
                                            ->label('Description')
                                            ->options(function (Get $get, $state, $record) {
                                                $prId = $get('../../pr_id');
                                                if (! $prId) {
                                                    return [];
                                                }

                                                $details = collect($get('../../purchaseRequestDetails') ?? []);
                                                $currentIndex = $details->search(function ($item) use ($state) {
                                                    return $item['desc'] === $state;
                                                });

                                                $selectedItems = $details
                                                    ->filter(function ($item, $index) use ($currentIndex) {
                                                        return $index !== $currentIndex;
                                                    })
                                                    ->pluck('desc')
                                                    ->filter()
                                                    ->toArray();

                                                return PurchaseRequestDetails::where('pr_id', $prId)
                                                    ->where('is_utilized', false)
                                                    ->whereNotIn('item_id', $selectedItems)
                                                    ->with('items')  // Eager load items relationship
                                                    ->get()
                                                    ->pluck('items.name', 'items.name')  // Use the correct relationship and column
                                                    ->toArray();
                                            })
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                if ($state) {
                                                    $prItem = PurchaseRequestDetails::where('pr_id', $get('../../pr_id'))
                                                        ->whereHas('items', function ($query) use ($state) {
                                                            $query->where('name', $state);
                                                        })
                                                        ->with('items', 'budgetAccount')
                                                        ->first();

                                                    if ($prItem) {
                                                        $set('unit_measure', $prItem->unit);
                                                        $set('qty', $prItem->amount);
                                                        $set('budget_account', $prItem->budgetAccount->id);
                                                        $set('itemcode', $prItem->items->item_code);
                                                    }
                                                }
                                            })
                                            ->disabled(fn (Get $get) => ! $get('../../pr_id'))
                                            ->required()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('unit_measure')
                                            ->label('U/M')
                                            ->required()
                                            ->disabled()
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('qty')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->columnSpan(2),
                                        Forms\Components\Radio::make('gst')
                                            ->label('GST(%)')
                                            ->inline()
                                            ->inlineLabel(false)
                                            ->default('0')
                                            ->columnSpan(2)
                                            ->options([
                                                '0' => '0%',
                                                '8' => '8%', ])
                                            ->required(),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
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
                Tables\Columns\TextColumn::make('purchaseRequest.pr_no')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('po_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->getStateUsing(fn ($record) => $record->payment_method === 'purchase_order' ? 'Purchase Order' : 'Petty Cash')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d-m-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('MVR', locale: 'us')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->purchaseOrderDetails->sum('amount')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => $record->status === PurchaseOrderStatus::Submitted->value ? 'Submitted' :
                    ($record->status === PurchaseOrderStatus::Reimbursed->value ? 'Reimbursed' :
                    ($record->status === PurchaseOrderStatus::Closed->value ? 'Closed' :
                    ($record->status === PurchaseOrderStatus::WaitingReimbursement->value ? 'Pending Reimbursement' : 'Draft')))
                    )
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Closed' => 'danger',
                        'Submitted' => 'warning',
                        'Draft' => 'gray',
                        'Reimbursment Pending' => 'warning',
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
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => (($record->status == PurchaseOrderStatus::Draft->value && Auth::user()->can('create_purchase::orders')) || (
                         Auth::user()->can('approve_purchase::requests')))),
                        
                Tables\Actions\Action::make('view_advance_form')
                    ->label('View Advance Form')
                    ->icon('heroicon-o-eye')
                    ->visible(fn ($record): bool => ! empty($record->advance_form_id))
                    ->url(fn ($record): string => route('purchase-orders.advance-form.download', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('purchase_order_submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status == PurchaseOrderStatus::Draft->value &&
                        Auth::user()->can('create_purchase::orders')
                    )
                    ->action(function (PurchaseOrders $record) {
                        $record->update([
                            'status' => PurchaseOrderStatus::Submitted->value,
                            'is_submitted' => true,
                        ]);
                        foreach ($record->purchaseOrderDetails as $detail) {

                            $itemid = Item::where('item_code', $detail->itemcode)->first()->id;

                            PurchaseRequestDetails::where('item_id', $itemid)->where('pr_id', $record->pr_id)->update([
                                'is_utilized' => true,
                            ]);
                            PurchaseRequests::checkAndUpdateClosedStatus($record->pr_id);
                        }
                        Notification::make()
                            ->title('PO Submitted successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('regenerate_advance_form')
                    ->label('Regenerate Advance Form')
                    ->icon('heroicon-o-document')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Advance Form Details')
                    ->modalSubheading('Please fill in the required fields')
                    ->modalButton('Generate')
                    ->visible(fn ($record) => $record->advance_form_id && $record->is_advance_form_required && $record->status == PurchaseOrderStatus::Submitted->value && $record->payment_method == 'purchase_order' &&
                            Auth::user()->can('create_purchase::orders')
                    )
                    ->form([
                        Forms\Components\TextInput::make('qoation_no')
                            ->label('Qoaution No')
                            ->required(),
                        Forms\Components\TextInput::make('expected_delivery')
                            ->label('Expected Delivery In Days')
                            ->required(),
                        Forms\Components\TextInput::make('advance_amount')
                            ->label('Advance Amount %')
                            ->numeric()
                            ->suffix('%')
                            ->required(),
                    ])
                    ->action(function (array $data, PurchaseOrders $record) {

                        // Create the Advance Form record with user inputs
                        $advanceForm = $record->advanceForm()->update([
                            'qoation_no' => $data['qoation_no'],
                            'expected_delivery' => $data['expected_delivery'],
                            'advance_percentage' => ($data['advance_amount']),
                            'advance_amount' => (($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount')),
                            'balance_amount' => $record->purchaseOrderDetails()->sum('amount') - ($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount'),
                            'generated_by' => Auth::id(),
                        ]);

                        // Redirect to the route that generates the PDF with the advance form data
                        return redirect()->route('purchase-orders.advance-form.download', $record);

                    }),
                Tables\Actions\Action::make('generate_advance_form')
                    ->label('Generate Advance Form')
                    ->icon('heroicon-o-document')
                    ->color('info')
                    ->modalHeading('Advance Form Details')
                    ->visible(fn ($record) => ! $record->advance_form_id && $record->is_advance_form_required && $record->status == PurchaseOrderStatus::Submitted->value && $record->payment_method == 'purchase_order' && Auth::user()->can('create_purchase::orders')
                    )
                    ->form([
                        Forms\Components\TextInput::make('qoation_no')
                            ->label('Qoaution No')
                            ->required(),
                        Forms\Components\TextInput::make('expected_delivery')
                            ->label('Expected Delivery In Days')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('advance_amount')
                            ->label('Advance Amount %')
                            ->numeric()
                            ->suffix('%')
                            ->required(),
                    ])
                    ->action(function (array $data, PurchaseOrders $record) {

                        $count = 1157 + 1;

                        do {
                            $request_number = sprintf('LADV/PROC/%04d', $count);
                            $exists = AdvanceForm::where('request_number', $request_number)->exists();
                            if ($exists) {
                                $count++;
                            }
                        } while ($exists);

                        // Create the Advance Form record with user inputs
                        $advanceForm = $record->advanceForm()->create([
                            'qoation_no' => $data['qoation_no'],
                            'expected_delivery' => $data['expected_delivery'],
                            'advance_percentage' => ($data['advance_amount']),
                            'advance_amount' => (($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount')),
                            'request_number' => $request_number,
                            'vendors_id' => $record->vendor_id,
                            'balance_amount' => $record->purchaseOrderDetails()->sum('amount') - ($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount'),
                            'generated_by' => Auth::id(),
                        ]);
                        $record->update([
                            'advance_form_id' => $advanceForm->id,
                        ]);

                        // Redirect to the route that generates the PDF with the advance form data
                        return redirect()->route('purchase-orders.advance-form.download', $record);

                    }),

                Tables\Actions\Action::make('upload_supporting_document')
                    ->label('Upload Reciept')
                    ->icon('heroicon-o-document')
                    ->visible(fn ($record) => $record->payment_method == 'petty_cash' && ! $record->supporting_document && Auth::user()->can('create_purchase::orders') && $record->status !== PurchaseOrderStatus::Closed->value)
                    ->form([
                        Forms\Components\FileUpload::make('supporting_document')
                            ->label('Document')
                            ->required(),
                    ])
                    ->action(function (PurchaseOrders $record, array $data) {
                        $record->update([
                            'supporting_document' => $data['supporting_document'],
                        ]);
                        Notification::make()
                            ->title('Document uploaded successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view_supporting_document')
                    ->label('View Reciept')
                    ->icon('heroicon-o-eye')
                    ->visible(fn ($record) => $record->supporting_document)
                    ->url(fn ($record) => asset('storage/'.$record->supporting_document))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('purchase_order_close')
                    ->label('Close')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to close this PR? This action cannot be undone.')
                    ->visible(fn ($record) => $record->status == PurchaseOrderStatus::Submitted->value && Auth::user()->can('approve_purchase::requests')
                    || $record->status == PurchaseOrderStatus::Submitted->value && $record->supporting_document && $record->payment_method == 'petty_cash' && Auth::user()->can('create_purchase::orders')
                    )
                    ->action(function (PurchaseOrders $record) {
                        if ($record->payment_method == 'petty_cash') {
                            $record->update([
                                'status' => PurchaseOrderStatus::WaitingReimbursement->value,
                                'is_closed_by' => Auth::id(),
                            ]);
                        } else {
                            $record->update([
                                'status' => PurchaseOrderStatus::Closed->value,
                                'is_closed_by' => Auth::id(),
                            ]);
                        }

                        if ($record->payment_method == 'purchase_order') {

                            foreach ($record->purchaseOrderDetails as $detail) {
                                $detail->budgetAccount->update([
                                    'amount' => $detail->budgetAccount->amount - $detail->amount, ]);
                                BudgetTransactionHistory::createtransaction($detail->budgetAccount->id, 'Purchase Order', $detail->amount, $detail->budgetAccount->amount, 'Purchase Order Closed for PO ('.$record->po_no.' | Item: '.$detail->desc.' )', Auth::id());
                            }
                            // $record->purchaseRequest->budgetAccount->update([
                            //     'amount' => $record->purchaseRequest->budgetAccount->amount - $record->purchaseOrderDetails()->sum('amount'),
                            // ]);

                            // BudgetTransactionHistory::createtransaction($record->purchaseRequest->budgetAccount->id, 'Purchase Order', $record->purchaseOrderDetails()->sum('amount'), $record->purchaseRequest->budgetAccount->amount, 'Purchase Order Closed', Auth::id());

                        }

                        Notification::make()
                            ->title('PO Closed successfully')
                            ->success()
                            ->send();
                    }),

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
            RelationManagers\PurchaseOrderDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrders::route('/create'),
            'edit' => Pages\EditPurchaseOrders::route('/{record}/edit'),
        ];
    }
}
