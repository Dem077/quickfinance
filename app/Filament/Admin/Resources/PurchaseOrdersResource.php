<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use App\Enums\UnitsEnum;
use App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;
use App\Filament\Admin\Resources\PurchaseOrdersResource\RelationManagers;
use App\Enums\AdvanceFormStatus;
use App\Models\AdvanceForm;
use App\Models\Item;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use Filament\Tables\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class PurchaseOrdersResource extends Resource implements HasShieldPermissions
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
            'md_dmd_approve_advance_form',
            'close',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('po_no')
                    ->label('Record ID')
                    ->required(fn (Get $get) => $get('payment_method') !== 'petty_cash')
                    ->visible(fn (Get $get) => $get('payment_method') !== 'petty_cash')
                    ->disabled(fn ($record) => $record && $record->status !== PurchaseOrderStatus::Draft)
                    ->maxLength(255),
                Forms\Components\Select::make('po_no')
                    ->label('Petty Cash Form Number')
                    ->options(fn (?PurchaseOrders $record) => PettyCashReimbursment::draftFormNoOptions(
                        $record?->payment_method === 'petty_cash' ? $record->po_no : null,
                        $record?->id,
                    ))
                    ->default(fn (Get $get, string $operation) => $operation === 'create' && $get('payment_method') === 'petty_cash'
                        ? PettyCashReimbursment::GENERATE_FORM_NO_OPTION
                        : null)
                    ->required(fn (Get $get) => $get('payment_method') === 'petty_cash')
                    ->visible(fn (Get $get) => $get('payment_method') === 'petty_cash')
                    ->native(false)
                    ->searchable()
                    ->disabled(fn ($record) => $record && $record->status !== PurchaseOrderStatus::Draft),
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->disabled(fn ($record) => $record && $record->status !== PurchaseOrderStatus::Draft)
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->disabled(fn ($record) => $record && $record->status !== PurchaseOrderStatus::Draft)
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('pr_id')
                    ->relationship('purchaseRequest', 'pr_no', function ($query) {
                        return $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->wherenot('status', PurchaseRequestsStatus::Closed);
                    })
                    ->preload()
                    ->live()
                    ->disabled(fn ($record) => $record && $record->status !== PurchaseOrderStatus::Draft)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $set('purchaseRequestDetails.*.pr_id', $state);
                    })
                    ->required(),
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->live()
                    ->disabled(fn ($record) => $record && $record->status !== PurchaseOrderStatus::Draft)
                    ->native(false)
                    ->options([
                        'purchase_order' => 'Purchase Order',
                        'petty_cash' => 'Petty Cash',
                    ])
                    ->default('purchase_order')
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $set('po_no', $state === 'petty_cash'
                            ? PettyCashReimbursment::GENERATE_FORM_NO_OPTION
                            : null);
                    })
                    ->required(),
                Forms\Components\Radio::make('is_advance_form_required')
                    ->label('Advance Form Required')
                    ->inline()
                    ->disabled(fn ($record) => $record && $record->status !== PurchaseOrderStatus::Draft)
                    ->inlineLabel(false)
                    ->default('0')
                    ->options([
                        0 => 'No',
                        1 => 'Yes',
                    ])
                    ->visible(fn (Get $get) => $get('payment_method') === 'purchase_order')
                    ->required(),
                Forms\Components\Fieldset::make('Item to be Utilized')
                    ->columns(['md' => 7, 'lg' => 7])
                    ->schema([
                        Forms\Components\Repeater::make('purchaseOrderDetails')
                            ->label('Items / Services')
                            ->columnSpanFull()
                            ->extraItemActions([
                                Forms\Components\Actions\Action::make('calculate_all')
                                    ->label('Generate Total')
                                    ->icon('heroicon-m-calculator')
                                    ->color('info')
                                    ->tooltip('Generate Grand Total')
                                    ->action(function (Get $get, Set $set) {
                                        $items = $get('purchaseOrderDetails');
                                        if (! is_array($items)) {
                                            return;
                                        }

                                        $subtotal = collect($items)->sum(fn ($item) => (float) ($item['amount'] ?? 0));
                                        $gsttotal = collect($items)->sum(fn ($item) => (float) ($item['tax_amount'] ?? 0));

                                        $set('gst_total', round($gsttotal, 3));
                                        $set('total_amount', round($subtotal, 3));
                                    }),
                            ])
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(14)
                                    ->schema([
                                        //                                        Forms\Components\TextInput::make('itemcode')
                                        //                                            ->label('Item Code')
                                        //                                            ->required()
                                        //                                            ->disabled()
                                        //                                            ->live()
                                        //                                            ->dehydrated(false)
                                        //                                            ->columnSpan(2),
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
                                                    ->pluck('items.name', 'id')  // Use the correct relationship and column
                                                    ->toArray();
                                            })
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                if ($state) {
                                                    $prItem = PurchaseRequestDetails::where('pr_id', $get('../../pr_id'))
                                                        ->where('id', $state)
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
                                        Forms\Components\Select::make('unit_measure')
                                            ->label('U/M')
                                            ->native(false)
                                            ->options(UnitsEnum::class)
                                            ->required()
                                            ->disabled()
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
                                            ->live()
                                            ->reactive()
                                            ->columnSpan(2)
                                            ->options([
                                                '0' => '0%',
                                                '8' => '8%', ])
                                            ->required(),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->hintAction(
                                                Forms\Components\Actions\Action::make('generate_amounts')
                                                    ->iconButton()
                                                    ->icon('heroicon-m-plus-circle')
                                                    ->action(function ($record, Get $get, Set $set, $state) {
                                                        $qty = (float) ($get('qty') ?? 0);
                                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                                        $gst = $get('gst');
                                                        $subtotal = $qty * $unitPrice;
                                                        $gstamount = $subtotal * ($gst / 100);
                                                        $amount = $subtotal + $gstamount;
                                                        $set('tax_amount', round($gstamount));
                                                        $set('amount', round($amount, 3));

                                                    }))
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('tax_amount')
                                            ->label('Tax amount')
                                            ->numeric()
                                            ->reactive()
                                            ->live()
                                            ->disabled(function (Get $get) {
                                                $gst = $get('gst');

                                                return $gst === '0';
                                            })
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                $qty = (float) ($get('qty') ?? 0);
                                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                                $subtotal = $qty * $unitPrice;
                                                $amount = $subtotal + (float) $state;
                                                $set('amount', round($amount, 3));
                                            })
                                            ->required()
                                            ->mutateDehydratedStateUsing(fn ($state) => round((float) $state, 3))
                                            ->formatStateUsing(fn ($state) => number_format((float) $state, 3, '.', ''))
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->mutateDehydratedStateUsing(fn ($state) => round((float) $state, 3))
                                            ->formatStateUsing(fn ($state) => number_format((float) $state, 3, '.', ''))
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minItems(1),
                        Forms\Components\Hidden::make('test')
                            ->columnSpan(3),
                        Forms\Components\TextInput::make('gst_total')
                            ->label('Tax Amount')
                            ->inlineLabel()
                            ->live()
                            ->reactive()
                            ->disabled()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->inlineLabel()
                            ->disabled()
                            ->live()
                            ->reactive()
                            ->formatStateUsing(function (Get $get) {
                                $items = $get('purchaseOrderDetails') ?? [];
                                $total = collect($items)->sum(fn ($item) => (float) ($item['amount'] ?? 0));

                                return number_format($total, 3, '.', '');
                            })
                            ->live()
                            ->columnSpan(2),
                    ])->hidden(fn (string $operation): bool => $operation !== 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Grid::make([
                        'lg' => 2,
                    ])
                        ->schema([
                            Tables\Columns\TextColumn::make('status')
                                ->sortable()
                                ->alignCenter()
                                ->columnSpanFull()
                                ->searchable()
                                ->size('lg')
                                ->weight('bold')
                                ->extraAttributes(['class' => 'w-100'])
                                ->badge(),
                            Tables\Columns\TextColumn::make('purchaseRequest.pr_no')
                                ->description('Purchase Request', 'above')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('vendor.name')
                                ->description('Vendor', 'above')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('po_no')
                                ->description('PO Number', 'above')
                                ->searchable(),
                            Tables\Columns\TextColumn::make('payment_method')
                                ->description('Payment Method', 'above')
                                ->getStateUsing(fn ($record) => $record->payment_method === 'purchase_order' ? 'Purchase Order' : 'Petty Cash')
                                ->sortable(),
                            Tables\Columns\TextColumn::make('date')
                                ->description('Date', 'above')
                                ->date('d-m-Y')
                                ->sortable(),
                            Tables\Columns\TextColumn::make('total_amount')
                                ->description('Total Amount', 'above')
                                ->money('MVR')
                                ->sortable()
                                ->getStateUsing(fn ($record) => $record->purchaseOrderDetails->sum('amount')),
                            Tables\Columns\TextColumn::make('advanceForm.status')
                                ->sortable()
                                ->description('Advanceform Status', 'above')
                                ->alignleft()
                                ->columnSpanFull()
                                ->searchable()
                                ->size('lg')
                                ->weight('bold')
                                ->extraAttributes(['class' => 'w-100'])
                                ->badge(),

                        ]),
                ])->space(3)->extraAttributes([
                    'class' => 'pb-2',
                ]),

            ])
            ->defaultSort('date', 'desc')
            ->contentGrid([
                'sm' => 1,
                'md' => 1,
                'xl' => 2,
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PurchaseOrderStatus::class),
                SelectFilter::make('pr_id')
                    ->label('Purchase Request')
                    ->relationship('purchaseRequest', 'pr_no')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'purchase_order' => 'Purchase Order',
                        'petty_cash' => 'Petty Cash',
                    ]),
                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('Date From')->native(false),
                        Forms\Components\DatePicker::make('date_until')->label('Date To')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date)
                            )
                            ->when(
                                $data['date_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->button(),
                Tables\Actions\EditAction::make()
                    ->button()
                    ->visible(fn ($record) => $record->status === PurchaseOrderStatus::Draft && Auth::user()->can('create_purchase::orders')),

                
                Tables\Actions\Action::make('purchase_order_submit')
                    ->label('Submit')
                    ->button()
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status == PurchaseOrderStatus::Draft &&
                        Auth::user()->can('create_purchase::orders')
                    )
                    ->action(function (PurchaseOrders $record) {
                        $record->update([
                            'status' => PurchaseOrderStatus::Submitted,
                            'is_submitted' => true,
                        ]);
                        foreach ($record->purchaseOrderDetails as $detail) {

                            $itemid = Item::where('item_code', $detail->itemcode)->first()->id;

                        }
                        Notification::make()
                            ->title('PO Submitted successfully')
                            ->success()
                            ->send();
                    }),
                ActionGroup::make([
                    Tables\Actions\Action::make('submite_advance_form')
                        ->label('Submit Form')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        //->button()
                        ->requiresConfirmation()
                        ->modalHeading('Advance Form Details')
                        ->modalSubheading('Please fill in the required fields')
                        ->modalButton('Submit')
                        ->visible(fn ($record) => $record->advance_form_id
                            && $record->is_advance_form_required
                            && $record->status == PurchaseOrderStatus::Submitted
                            && $record->payment_method == 'purchase_order'
                            && Auth::user()->can('create_purchase::orders')
                            && $record->advanceForm?->status === AdvanceFormStatus::Draft
                            && $record->advanceForm?->generated_by == Auth::id()
                        )
                        ->action(function (PurchaseOrders $record) {
                            $record->advanceForm?->update([
                                'status' => AdvanceFormStatus::Submitted,
                            ]);

                            Notification::make()
                                ->title('Advance form submitted successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('hod_approve_advance_form')
                        ->label('HOD Approve Form')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        //->button()
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->advance_form_id
                            && $record->is_advance_form_required
                            && $record->payment_method == 'purchase_order'
                            && $record->advanceForm?->status === AdvanceFormStatus::Submitted
                            && $record->advanceForm?->user->department->user->id == Auth::id()
                        )
                        ->action(function (PurchaseOrders $record) {
                            $record->advanceForm?->update([
                                'status' => AdvanceFormStatus::HOD_Approved,
                                'hod_approved_by' => Auth::id(),
                            ]);

                            Notification::make()
                                ->title('Advance form HOD approved successfully')
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\Action::make('hod_reject_advance_form')
                        ->label('HOD Reject Form')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        //->button()
                        ->requiresConfirmation()
                        ->visible(fn ($record) => 
                            $record->advance_form_id
                            && $record->is_advance_form_required
                            && $record->payment_method == 'purchase_order'
                            && $record->advanceForm?->status === AdvanceFormStatus::Submitted
                            && $record->advanceForm?->user->department->user->id == Auth::id()
                        )
                        ->action(function (PurchaseOrders $record) {
                            $record->advanceForm?->update([
                                'status' => AdvanceFormStatus::HOD_Rejected,
                            ]);

                            Notification::make()
                                ->title('Advance form HOD rejected successfully')
                                
                                ->danger()
                                ->send();
                        }),
                    Tables\Actions\Action::make('md_dmd_approve_advance_form')
                        ->label('MD / DMD Approve Form')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->advanceForm?->status === AdvanceFormStatus::HOD_Approved && Auth::user()->can('md_dmd_approve_advance_form_purchase::orders')
                        )
                        ->action(function (PurchaseOrders $record) {
                            $record->advanceForm?->update([
                                'status' => AdvanceFormStatus::DMD_MD_Approved,
                                'md_dmd_approved_by' => Auth::id(),
                            ]);

                            Notification::make()
                                ->title('Advance form MD / DMD approved successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('md_dmd_reject_advance_form')
                        ->label('MD / DMD Reject Form')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->advanceForm?->status === AdvanceFormStatus::HOD_Approved && Auth::user()->can('md_dmd_approve_advance_form_purchase::orders')
                        )
                        ->action(function (PurchaseOrders $record) {
                            $record->advanceForm?->update([
                                'status' => AdvanceFormStatus::DMD_MD_Rejected,
                            ]);

                            Notification::make()
                                ->title('Advance form MD / DMD rejected successfully')
                                ->danger()
                                ->send();
                        }),
                    Tables\Actions\Action::make('regenerate_advance_form')
                        ->label('Regenerate Form')
                        ->icon('heroicon-o-document')
                        ->color('warning')
                        //->button()
                        ->requiresConfirmation()
                        ->modalHeading('Advance Form Details')
                        ->modalSubheading('Please fill in the required fields')
                        ->modalButton('Generate')
                        ->visible(fn ($record) => $record->advance_form_id
                                && $record->is_advance_form_required
                                && $record->status == PurchaseOrderStatus::Submitted
                                && $record->payment_method == 'purchase_order'
                                && Auth::user()->can('generate_advance_form_purchase::orders')
                                && $record->advanceForm?->status === AdvanceFormStatus::Draft
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

                            $record->advanceForm()->update([
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
                        ->label('Generate Form')
                        ->icon('heroicon-o-document')
                        //->button()
                        ->color('info')
                        ->modalHeading('Advance Form Details')
                        ->visible(fn ($record) => ! $record->advance_form_id && $record->is_advance_form_required && $record->status == PurchaseOrderStatus::Submitted && $record->payment_method == 'purchase_order' && Auth::user()->can('generate_advance_form_purchase::orders')
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
                                'status' => AdvanceFormStatus::Draft,
                            ]);
                            $record->update([
                                'advance_form_id' => $advanceForm->id,
                            ]);

                            Notification::make()
                                ->title('Advance form generated successfully')
                                ->success()
                                ->send();
                            // Redirect to the route that generates the PDF with the advance form data
                           // return redirect()->route('purchase-orders.advance-form.download', $record);

                        }),
                    Tables\Actions\Action::make('view_advance_form')
                        ->label('View Form')
                        ->icon('heroicon-o-eye')
                       // ->button()
                        ->visible(fn ($record): bool => ! empty($record->advance_form_id))
                        ->url(fn ($record): string => route('purchase-orders.advance-form.download', $record))
                        ->openUrlInNewTab(),
                ])  
                ->button()
                ->label('Manage Advance Form'),
                

                Tables\Actions\Action::make('upload_supporting_document')
                    ->label('Upload Reciept')
                    ->icon('heroicon-o-document')
                    ->button()
                    ->visible(fn ($record) => $record->payment_method == 'petty_cash' && ! $record->supporting_document && Auth::user()->can('create_purchase::orders') && $record->status !== PurchaseOrderStatus::Closed)
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
                    ->button()
                    ->icon('heroicon-o-eye')
                    ->visible(fn ($record) => $record->supporting_document)
                    ->url(fn ($record) => asset('storage/'.$record->supporting_document))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('purchase_order_close')
                    ->label('Close')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->button()
                    ->modalDescription('Are you sure you want to close this PR? This action cannot be undone.')
                    ->visible(fn ($record) => $record->status == PurchaseOrderStatus::Submitted
                        && Auth::user()->can('close_purchase::orders')
                        && (
                            $record->payment_method === 'purchase_order'
                            || ($record->payment_method === 'petty_cash' && $record->supporting_document)
                        )
                    )
                    ->action(function (PurchaseOrders $record) {
                        if ($record->payment_method == 'petty_cash') {
                            $record->update([
                                'status' => PurchaseOrderStatus::WaitingReimbursement,
                                'is_closed_by' => Auth::id(),
                            ]);
                        } else {
                            $record->update([
                                'status' => PurchaseOrderStatus::Closed,
                                'is_closed_by' => Auth::id(),
                            ]);
                        }

                        PurchaseRequests::checkAndUpdateClosedStatus($record->pr_id);

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
            'view' => Pages\ViewPurchaseOrders::route('/{record}'),
        ];
    }
}
