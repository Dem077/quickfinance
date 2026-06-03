<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PurchaseRequestsStatus;
use App\Enums\UnitsEnum;
use App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;
use App\Filament\Admin\Resources\PurchaseRequestsResource\RelationManagers;
use App\Models\Departments;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequests;
use App\Models\SubBudgetAccounts;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PurchaseRequestsResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = PurchaseRequests::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 2;

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
            'approve',
            'md_dmd_approve',
            'cancel',
            'close',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        if (Auth::user()->hasRole('super_admin')) {
            return parent::getEloquentQuery(); // Super admin sees all records
        }

        if (Auth::user()->can('approve_purchase::requests')) {
            return parent::getEloquentQuery()->whereNot('status', PurchaseRequestsStatus::Draft->value);
        }

        if (Auth::user()->can('md_dmd_approve_purchase::requests')) {
            return parent::getEloquentQuery()->whereIn('status', [
                PurchaseRequestsStatus::Approved->value,
                PurchaseRequestsStatus::MD_DMD_Approved->value,
                PurchaseRequestsStatus::MD_DMD_Rejected->value,
                PurchaseRequestsStatus::Closed->value,
            ]);
        }

        if (Auth::user()->view_all_pr == true) {
            return parent::getEloquentQuery()->whereNot('status', PurchaseRequestsStatus::Draft->value);
        }

        if (Departments::where('hod', Auth::user()->id)->exists()) {
            $departmentIds = Departments::where('hod', Auth::user()->id)->pluck('id')->toArray();

            return parent::getEloquentQuery()
                ->where(function ($query) use ($departmentIds) {
                    $query->where('user_id', Auth::id())
                        ->orWhereHas('user', function ($subQuery) use ($departmentIds) {
                            $subQuery->whereIn('department_id', $departmentIds);
                        })
                        ->whereNot('status', PurchaseRequestsStatus::Draft->value);
                });
        }
        if (Auth::user()->can('view_purchase::requests') && Auth::user()->can('create_purchase::orders')) {
            return parent::getEloquentQuery()->where('status', PurchaseRequestsStatus::MD_DMD_Approved->value)->orwhere('status', PurchaseRequestsStatus::Closed->value);
        }

        return parent::getEloquentQuery()->where('user_id', Auth::id());
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
                    ->disabled(fn ($record) => $record?->user_id != Auth::user()->id && Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('locations')
                    ->relationship('locations', 'name')
                    ->native(false)
                    ->multiple()
                    ->preload()
                    ->disabled(fn ($record) => $record?->user_id != Auth::user()->id && Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                    ->required(),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->native(false)
                    ->disabled(fn ($record) => $record?->user_id != Auth::user()->id && Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests')),
                Forms\Components\FileUpload::make('supporting_document')
                    ->label('Supporting Document')
                    ->openable()
                    ->disabled(fn ($record) => $record?->user_id != Auth::user()->id && Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests')),

                Forms\Components\TextInput::make('purpose')
                    ->label('Purpose / Reason')
                    ->required()
                    ->disabled(fn ($record) => $record?->user_id != Auth::user()->id && Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                    ->maxLength(255),
                Forms\Components\Hidden::make('user_id')
                    ->disabled(fn ($record) => $record?->user_id != Auth::user()->id && Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                    ->default(fn () => Auth::id())
                    ->required(),
                Section::make('Details')
                    ->schema([
                        Forms\Components\Repeater::make('purchaseRequestDetails')
                            ->label('Items / Services')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(10)
                                    ->schema([
                                        Forms\Components\Select::make('item')
                                            ->label('Item / Service')
                                            ->searchable()
                                            ->options(
                                                \App\Models\Item::all()->pluck('name', 'id')
                                            )
                                            ->required()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->columnSpan(2),
                                        Forms\Components\Select::make('unit')
                                            ->required()
                                            ->searchable()
                                            ->native(false)
                                            ->options(UnitsEnum::class)
                                            ->columnSpan(2),
                                        Forms\Components\Select::make('budget_account')
                                            ->label('Budget Account')
                                            ->options(function () {
                                                $departmentId = Auth::user()?->department_id;

                                                return \App\Models\SubBudgetAccounts::with(['allocations' => function ($query) use ($departmentId) {
                                                    if ($departmentId) {
                                                        $query->where('department_id', $departmentId);
                                                    }
                                                }, 'allocations.department'])
                                                    ->get()
                                                    ->filter(function ($row) use ($departmentId) {
                                                        return $departmentId
                                                            ? $row->allocations->firstWhere('department_id', $departmentId)
                                                            : $row->allocations->isNotEmpty();
                                                    })
                                                    ->mapWithKeys(fn ($row) => [
                                                        $row->id => $row->getSelectLabel(),
                                                    ])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->reactive()
                                            ->helperText(function (Forms\Get $get): string {
                                                $budgetAccountId = $get('budget_account');
                                                $departmentId = Auth::user()?->department_id;

                                                if (! $budgetAccountId || ! $departmentId) {
                                                    return 'Select a budget account to view your department allocation.';
                                                }

                                                $account = SubBudgetAccounts::with([
                                                    'allocations' => fn ($query) => $query->where('department_id', $departmentId),
                                                ])->find($budgetAccountId);

                                                $allocatedAmount = $account?->allocations->first()?->amount;

                                                if ($allocatedAmount === null) {
                                                    return 'No allocation found for your department.';
                                                }

                                                return 'Allocated budget: MVR '.number_format((float) $allocatedAmount, 2);
                                            })
                                            ->required()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('amount')
                                            ->label('Quantity')
                                            ->required()
                                            ->numeric()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('est_cost')
                                            ->label('Estimated Cost')
                                            ->required()
                                            ->numeric()
                                            ->reactive()
                                            ->rules([
                                                fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                                    if (empty($value)) {
                                                        return;
                                                    }
                                                    $budgetAccountId = $get('budget_account');
                                                    if ($budgetAccountId) {
                                                        $departmentId = Auth::user()?->department_id;

                                                        $account = SubBudgetAccounts::with([
                                                            'allocations' => fn ($query) => $query->where('department_id', $departmentId),
                                                        ])->find($budgetAccountId);

                                                        $departmentAllocation = $account?->allocations->first();

                                                        if (! $departmentAllocation) {
                                                            $fail('This budget code is not allocated to your department.');

                                                            return;
                                                        }

                                                        if ($value > $departmentAllocation->amount) {
                                                            $fail("You don't have enough funds for this budget code.");
                                                        }
                                                    }
                                                },
                                            ])
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minItems(1),
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
                            Tables\Columns\TextColumn::make('pr_no')
                                ->description('Purchase Request', 'above')
                                ->searchable(),
                            Tables\Columns\TextColumn::make('locations_display')
                                ->description('Location(s)', 'above')
                                ->getStateUsing(function ($record) {
                                    if (! $record) {
                                        return 'N/A';
                                    }
                                    $names = [];
                                    if ($record->location) {
                                        $names[] = $record->location->name;
                                    }
                                    if ($record->locations && $record->locations->count()) {
                                        foreach ($record->locations as $loc) {
                                            if (! in_array($loc->name, $names)) {
                                                $names[] = $loc->name;
                                            }
                                        }
                                    }

                                    return implode(', ', $names) ?: 'N/A';
                                })
                                ->searchable(query: fn (Builder $query, string $search) => $query->where(function ($query) use ($search) {
                                    $query->whereHas('location', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                        ->orWhereHas('locations', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                                })),
                            Tables\Columns\TextColumn::make('date')
                                ->description('Date', 'above')
                                ->date()
                                ->sortable(),  Tables\Columns\TextColumn::make('total_est_cost')
                                ->description('Total Estimated Cost', 'above')
                                ->getStateUsing(fn ($record) => $record?->purchaseRequestDetails?->sum('est_cost') ?? 0)
                                ->numeric()
                                ->money('MVR')
                                ->sortable(),
                            Tables\Columns\TextColumn::make('user.name')
                                ->description('Requested By', 'above')
                                ->numeric()
                                ->sortable(),

                            Tables\Columns\TextColumn::make('project.name')
                                ->description('Project', 'above')
                                ->sortable(),
                            Tables\Columns\TextColumn::make('purpose')
                                ->description('Purpose / Reason', 'above')
                                ->searchable()
                                ->wrap()
                                ->words(5)
                                ->sortable(),
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
                    ->label('Status')
                    ->options(PurchaseRequestsStatus::class),
                //                    ->default(fn () => Auth::user()->can('approve_purchase::requests') ? 'hod_approved' : ''),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->button(),
                Tables\Actions\Action::make('submit_for_approval')
                    ->label('Submit for Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->button()
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Draft &&
                        Auth::user()->can('send_approval_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record, User $user) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::Submitted->value,
                            // 'is_submited' => true,
                        ]);

                        Notification::make()
                            ->title('Submitted for approval successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('approve_purchase_request_hod')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Submitted && $record->user->department->user->id == Auth::user()->id
                    )
                    ->action(function (PurchaseRequests $record) {
                        $record->update([
                            'approved_by_hod' => Auth::id(),
                            'status' => PurchaseRequestsStatus::HODApproved,
                        ]);

                        Notification::make()
                            ->title('PR Approved successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reject_purchase_request_hod')
                    ->label('Reject and Send Back To Draft')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Submitted && $record->user->department->user->id == Auth::user()->id
                    )
                    ->action(function (PurchaseRequests $record) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::Draft,
                        ]);

                        Notification::make()
                            ->title('PR Rejected successfully')

                            ->danger()
                            ->send();
                    }),

                // Finance Approval
                Tables\Actions\Action::make('approve_purchase_request')
                    ->label('Approve')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::HODApproved &&
                        Auth::user()->can('approve_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::Approved,
                            'approved_canceled_by' => Auth::id(),
                        ]);

                        Notification::make()
                            ->title('PR Approved successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reject_purchase_request')
                    ->label('Reject')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('cancel_remark')
                            ->label('Cancellation Reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::HODApproved &&
                        Auth::user()->can('approve_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record, array $data) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::Rejected,
                            // 'is_canceled' => true,
                            'cancel_remark' => $data['cancel_remark'],
                            'approved_canceled_by' => Auth::id(),
                        ]);

                        Notification::make()
                            ->title('PR Rejected successfully')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('cancel_purchase_request')
                    ->label('Cancel')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('cancel_remark')
                            ->label('Cancellation Reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->visible(fn ($record) => $record->status !== PurchaseRequestsStatus::Draft &&
                        Auth::user()->can('cancel_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record, array $data) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::Canceled,
                            // 'is_canceled' => true,
                            'cancel_remark' => $data['cancel_remark'],
                            'approved_canceled_by' => Auth::id(),
                        ]);

                        Notification::make()
                            ->title('PR Canceled successfully')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('send_back_purchase_request')
                    ->label('Send Back To Draft')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->button()
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::HODApproved &&
                        Auth::user()->can('approve_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record, array $data) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::Draft,
                            // 'is_canceled' => true,
                        ]);

                        Notification::make()
                            ->title('PR Canceled successfully')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('approve_purchase_close')
                    ->label('Close')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->button()
                    ->modalHeading('Close Purchase Request')
                    ->modalDescription(fn (PurchaseRequests $record) => $record->openPurchaseOrdersForClose()->isNotEmpty()
                        ? 'Enter GRN numbers for related purchase orders. The PR and those purchase orders will be closed.'
                        : 'Are you sure you want to close this PR? This action cannot be undone.')
                    ->form(fn (PurchaseRequests $record): array => static::getCloseFormSchema($record))
                    ->requiresConfirmation(fn (PurchaseRequests $record) => $record->openPurchaseOrdersForClose()->isEmpty())
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::MD_DMD_Approved &&
                        Auth::user()->can('close_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record, array $data) {
                        static::closePurchaseRequest($record, $data);

                        Notification::make()
                            ->title('PR Closed successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('approve_purchase_request_md_dmd')
                    ->label('MD / DMD Approve')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Approved &&
                        Auth::user()->can('md_dmd_approve_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::MD_DMD_Approved,
                           
                            'approved_by_md_dmd' => Auth::id(),
                        ]);

                        Notification::make()
                            ->title('PR approved by MD / DMD successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reject_purchase_request_md_dmd')
                    ->label('MD / DMD Reject')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Approved &&
                        Auth::user()->can('md_dmd_approve_purchase::requests')
                    )
                    ->action(function (PurchaseRequests $record) {
                        $record->update([
                            'status' => PurchaseRequestsStatus::MD_DMD_Rejected,
                            
                            'approved_by_md_dmd' => Auth::id(),
                        ]);

                        Notification::make()
                            ->title('PR rejected by MD / DMD successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view_document')
                    ->label('View Document')
                    ->button()
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->visible(fn ($record) => filled($record->uploaded_document)
                        && Storage::disk('public')->exists($record->uploaded_document))
                    ->url(fn ($record) => asset('storage/'.$record->uploaded_document))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->button()
                    ->visible(fn ($record) => in_array($record->status, [PurchaseRequestsStatus::Approved, PurchaseRequestsStatus::MD_DMD_Approved]) && Auth::user()->can('send_approval_purchase::requests'))
                    ->url(fn (PurchaseRequests $record) => route('purchase-requests.download', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->button()
                    ->visible(fn ($record) => ($record->status == PurchaseRequestsStatus::HODApproved && Auth::user()->can('approve_purchase::requests')) || ($record->status == PurchaseRequestsStatus::Draft && Auth::user()->can('send_approval_purchase::requests')) || ($record->status == PurchaseRequestsStatus::Submitted && $record->user->department->user->id == Auth::user()->id)),
            ])
//            ->recordUrl(false)
            ->bulkActions([
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
            'view' => Pages\ViewPurchaseRequests::route('/{record}'),
            'edit' => Pages\EditPurchaseRequests::route('/{record}/edit'),
        ];
    }

    public static function getCloseFormSchema(PurchaseRequests $record): array
    {
        $purchaseOrders = $record->openPurchaseOrdersForClose();

        if ($purchaseOrders->isEmpty()) {
            return [];
        }

        return [
            Forms\Components\Repeater::make('purchase_order_grns')
                ->label('GRN Numbers for Purchase Orders')
                ->schema([
                    Forms\Components\Hidden::make('po_id'),
                    Forms\Components\TextInput::make('grn_number')
                        ->label('GRN Number')
                        ->required()
                        ->maxLength(255),
                ])
                ->default($purchaseOrders->map(fn (PurchaseOrders $purchaseOrder) => [
                    'po_id' => $purchaseOrder->id,
                    'grn_number' => $purchaseOrder->grn_number ?? '',
                ])->values()->all())
                ->itemLabel(fn (array $state): ?string => PurchaseOrders::find($state['po_id'] ?? null)?->po_no)
                ->addable(false)
                ->deletable(false)
                ->reorderable(false),
        ];
    }

    public static function closePurchaseRequest(PurchaseRequests $record, array $data): void
    {
        if (! empty($data['purchase_order_grns'])) {
            $record->applyGrnNumbersForClose($data['purchase_order_grns']);
        }

        $record->update([
            'status' => PurchaseRequestsStatus::Closed,
            'is_closed_by' => Auth::id(),
        ]);
    }
}
