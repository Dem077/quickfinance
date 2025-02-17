<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;
use App\Filament\Admin\Resources\PurchaseOrdersResource\RelationManagers;
use App\Models\AdvanceForm;
use App\Models\BudgetTransactionHistory;
use App\Models\Item;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Models\SubBudgetAccounts;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Laravel\SerializableClosure\Serializers\Native;

class PurchaseOrdersResource extends Resource
{
    protected static ?string $model = PurchaseOrders::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 3;
    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('po_no')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('pr_id')
                    ->relationship('purchaseRequest', 'pr_no', function ($query) {
                        return $query->whereNotNull('uploaded_document')->where('is_closed', false);
                    })
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $set('purchaseRequestDetails.*.pr_id', $state);
                    })
                    ->required(),
                    Select::make('payment_method')
                        ->label('Payment Method')
                        ->live()
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
                                        if (!is_array($items)) return;
                                        
                                        foreach ($items as $key => $item) {
                                            $qty = (float) ($item['qty'] ?? 0);
                                            $gst = (float) ($item['gst'] ?? 0);
                                            $unitPrice = (float) ($item['unit_price'] ?? 0);
                                            $set("purchaseOrderDetails.{$key}.amount", $qty * $unitPrice + ($qty * $unitPrice * ($gst / 100)));
                                        }
                                    })
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
                                                if (!$prId) return [];
                                                
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
                                                        ->with('items','budgetAccount')
                                                        ->first();

                                                    if ($prItem) {
                                                        $set('unit_measure', $prItem->unit);
                                                        $set('qty', $prItem->amount);
                                                        $set('budget_account', $prItem->budgetAccount->id);
                                                        $set('itemcode', $prItem->items->item_code);
                                                    }
                                                }
                                            })
                                            ->disabled(fn (Get $get) => !$get('../../pr_id'))
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
                                            ->default('8')
                                            ->columnSpan(2)
                                            ->options([
                                                '0' => '0%',
                                                '8' => '8%',])
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
                    ->getStateUsing(fn ($record) => 
                        ($record->is_closed ? 'Closed' :
                        ($record->is_submitted ? 'Submitted' : 'Draft'))
                    )
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Closed' => 'danger',
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
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => ($record->is_closed == false && $record->is_submitted == false && Auth::user()->can('send_approval_purchase::requests'))),
                Tables\Actions\Action::make('view_advance_form')
                    ->label('View Advance Form')
                    ->icon('heroicon-o-eye')
                    ->visible(fn ($record): bool => !empty($record->advance_form_id))
                    ->url(fn ($record): string => route('purchase-orders.advance-form.download', $record->advance_form_id))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('approve_purchase_close')
                    ->label('Close')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to close this PR? This action cannot be undone.')
                    ->visible(fn ($record) => $record->is_submitted && !$record->is_closed &&
                        Auth::user()->can('approve_purchase::requests') || $record->is_submitted && !$record->is_closed && $record->supporting_document  && $record->payment_method == 'petty_cash' && Auth::user()->can('send_approval_purchase::requests')
                    )
                    ->action(function (PurchaseOrders $record) {
                        $record->update([
                            'is_closed' => true,
                            'is_closed_by' => Auth::id(),
                        ]);
                        if($record->payment_method == 'purchase_order'){
                            
                            foreach($record->purchaseOrderDetails as $detail){
                                 $detail->budgetAccount->update([
                                    'amount' => $detail->budgetAccount->amount - $detail->amount,]);
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
                Tables\Actions\Action::make('approve_purchase_submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->is_submitted && !$record->is_closed &&
                        Auth::user()->can('send_approval_purchase::requests' ) && $record->payment_method == 'purchase_order' || !$record->is_submitted && !$record->is_closed && $record->supporting_document  && $record->payment_method == 'petty_cash' && Auth::user()->can('send_approval_purchase::requests')
                    )
                    ->action(function (PurchaseOrders $record) {
                        $record->update([
                            'is_submitted' => true,
                        ]);
                        foreach ($record->purchaseOrderDetails as $detail) {
                            
                            $itemid = Item::where('item_code', $detail->itemcode)->first()->id;
                            
                            PurchaseRequestDetails::where('item_id',$itemid)->where('pr_id' , $record->pr_id )->update([
                                'is_utilized' => true,
                            ]);
                            PurchaseRequests::checkAndUpdateClosedStatus( $record->pr_id);
                        }
                        Notification::make()
                            ->title('PO Submitted successfully')
                            ->success()
                            ->send();
                }),
                Tables\Actions\Action::make('generate_advance_form')
                    ->label('Generate Advance Form')
                    ->icon('heroicon-o-document')
                    ->color('info')
                    ->modalHeading('Advance Form Details')
                    ->modalSubheading('Please fill in the required fields')
                    ->modalButton('Generate')
                    ->visible(fn ($record) => !$record->advance_form_id && $record->is_advance_form_required && $record->is_submitted && !$record->is_closed && $record->payment_method == 'purchase_order' &&
                        Auth::user()->can('send_approval_purchase::requests')
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
                        // Add additional fields as needed
                    ])
                    ->action(function (array $data, PurchaseOrders $record) {

                        $count = 1125 + 1;

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
                            'advance_amount' => (($data['advance_amount']/100)*$record->purchaseOrderDetails()->sum('amount')),
                            'request_number' => $request_number,
                            'vendors_id' => $record->vendor_id,
                            'balance_amount' => $record->purchaseOrderDetails()->sum('amount') - ($data['advance_amount']/100)*$record->purchaseOrderDetails()->sum('amount'),
                            'generated_by' => Auth::id(),
                        ]);
                        $record->update([
                            'advance_form_id' => $advanceForm->id,
                        ]);

                        // Redirect to the route that generates the PDF with the advance form data
                        return redirect()->route('purchase-orders.advance-form.download', $record->advance_form_id);
                           
                    }),
                Tables\Actions\Action::make('upload_document')
                    ->label('Upload Support')
                    ->icon('heroicon-o-document')
                    ->visible(fn ($record) => $record->payment_method == 'petty_cash' && !$record->supporting_document && Auth::user()->can('send_approval_purchase::requests'))
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
                Tables\Actions\Action::make('supporting_document')
                    ->label('View Document')
                    ->icon('heroicon-o-eye')
                    ->visible(fn ($record) => $record->supporting_document)
                    ->url(fn ($record) => asset('storage/' . $record->supporting_document))
                    ->openUrlInNewTab(), 
            
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
