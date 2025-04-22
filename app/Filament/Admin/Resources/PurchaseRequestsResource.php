<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;
use App\Filament\Admin\Resources\PurchaseRequestsResource\RelationManagers;
use App\Mail\NotificationEmail;
use App\Mail\StatusEmail;
use App\Models\Departments;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequests;
use App\Models\SubBudgetAccounts;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests')|| Auth::user()->is_hod == true && !Auth::user()->can('send_approval_purchase::requests'))
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('location_id')
                    ->relationship('location', 'name')
                    ->native(false)
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests')|| Auth::user()->is_hod == true && !Auth::user()->can('send_approval_purchase::requests'))
                    ->required(),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->native(false)
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests')|| Auth::user()->is_hod == true && !Auth::user()->can('send_approval_purchase::requests')),
                Forms\Components\FileUpload::make('supporting_document')
                    ->label('Supporting Document')
                    ->openable()
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests')|| Auth::user()->is_hod == true && !Auth::user()->can('send_approval_purchase::requests')),
         
                Forms\Components\TextInput::make('purpose')
                    ->label('Purpose / Reason')
                    ->required()
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests')|| Auth::user()->is_hod == true && !Auth::user()->can('send_approval_purchase::requests'))
                    ->maxLength(255),
                Forms\Components\Hidden::make('user_id')
                    ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests')|| Auth::user()->is_hod == true && !Auth::user()->can('send_approval_purchase::requests'))
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
                                            ->options([
                                                'Kg' => 'Kg',
                                                'Case' => 'Case',
                                                'Pcs' => 'Pcs',
                                                'Ltr' => 'Ltr',
                                                'Each' => 'Each',
                                                'Bottle' => 'Bottle',
                                                'Bags' => 'Bags',
                                                'Feet' => 'Feet',
                                                'Meter' => 'Meter',
                                                '-' => '-',
                                            ])
                                            ->columnSpan(2),
                                        Forms\Components\Select::make('budget_account')
                                            ->label('Budget Account')
                                            ->options(function () {
                                                return \App\Models\SubBudgetAccounts::with('department')
                                                    ->get()
                                                    ->mapWithKeys(function ($row) {
                                                        return [
                                                            $row->id => $row->code . ' - ' . $row->name . 
                                                                ($row->department ? ' (' . $row->department->name . 
                                                                (isset($row->location) ? ' / ' . $row->location->name : '') . ')' : ''),
                                                        ];
                                                    })
                                                    ->toArray();
                                            })
                                            ->searchable()
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
                                                fn (Forms\Get $get): Closure =>
                                                    function (string $attribute, $value, Closure $fail) use ($get) {
                                                        if (empty($value)) {
                                                            return;
                                                        }
                                                        $budgetAccountId = $get('budget_account');
                                                        if ($budgetAccountId) {
                                                            $account = SubBudgetAccounts::find($budgetAccountId);
                                                            if ($account && $value > $account->amount) {
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
                Tables\Columns\TextColumn::make('location.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Purpose / Reason')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_est_cost')
                    ->label('Total Estimated Cost')
                    ->getStateUsing(fn ($record) => $record->purchaseRequestDetails->sum('est_cost'))
                    ->numeric()
                    ->money('MVR', locale: 'us')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => 
                        $record->status === PurchaseRequestsStatus::Submitted->value ? 'Submitted' : 
                        ($record->status === PurchaseRequestsStatus::HODApproved->value ? 'Department HOD Approved' : 
                        ($record->status === PurchaseRequestsStatus::HODRejected->value ? 'Department HOD Rejected' : 
                        ($record->status === PurchaseRequestsStatus::Approved->value ? 'Finance Approved' : 
                        ($record->status === PurchaseRequestsStatus::Canceled->value ? 'Finance Rejected' : 
                        ($record->status === PurchaseRequestsStatus::Closed->value ? 'Closed' :
                        ($record->status === PurchaseRequestsStatus::DocumentUploaded->value ? 'Document Uploaded' :'Draft') ))))))
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Closed' => 'success',
                        'Department HOD Approved' => 'success',
                        'Finance Approved' => 'success',
                        'Document Uploaded' => 'info',
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

            ])
            ->actions([
                    Tables\Actions\Action::make('submit_for_approval')
                        ->label('Submit for Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status==PurchaseRequestsStatus::Draft->value &&
                            Auth::user()->can('send_approval_purchase::requests')
                        )
                        ->action(function (PurchaseRequests $record , User $user) {
                            $record->update([
                                'status' => PurchaseRequestsStatus::Submitted->value,
                                // 'is_submited' => true,
                            ]);
                            
                            $hod = $record->user->department->user->email;

                            
                                Mail::to($hod)->queue(new NotificationEmail('Purchase Request '. $record->pr_no));
                            

                            Notification::make()
                                ->title('Submitted for approval successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('approve_purchase_request_hod')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status==PurchaseRequestsStatus::Submitted->value && Auth::user()->department->user->id == Auth::user()->id
                        )
                        ->action(function (PurchaseRequests $record) {
                            $record->update([
                                'approved_by_hod' => Auth::id(),
                                'status' => PurchaseRequestsStatus::HODApproved->value,
                            ]);
                            $user = User::find($record->user_id);
                            Mail::to($user->email)->queue(new StatusEmail('Purchase Request '. $record->pr_no, 'approved', '','HOD'));

                            Notification::make()
                                ->title('PR Approved successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('reject_purchase_request_hod')
                        ->label('Reject')
                        ->icon('heroicon-o-check-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->status==PurchaseRequestsStatus::Submitted->value && Auth::user()->department->user->id == Auth::user()->id
                        )
                        ->action(function (PurchaseRequests $record) {
                            $record->update([
                                'is_approved_by_hod' => True,
                                'approved_by_hod' => Auth::id(),//Actually to record who rejected
                                'status' => PurchaseRequestsStatus::HODRejected->value,
                            ]);
                            $user = User::find($record->user_id);
                            Mail::to($user->email)->queue(new StatusEmail('Purchase Request '. $record->pr_no, 'rejected', '','HOD'));

                            Notification::make()
                                ->title('PR Approved successfully')
                                ->success()
                                ->send();
                        }),

                    //Finance Approval
                    Tables\Actions\Action::make('approve_purchase_request')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status==PurchaseRequestsStatus::HODApproved->value &&
                            Auth::user()->can('approve_purchase::requests')
                        )
                        ->action(function (PurchaseRequests $record) {
                            $record->update([
                                'status' => PurchaseRequestsStatus::Approved->value,
                                'approved_canceled_by' => Auth::id(),
                            ]);
                            $user = User::find($record->user_id);
                            Mail::to($user->email)->queue(new StatusEmail('Purchase Request '. $record->pr_no, 'approved', '',''));

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
                                ->maxLength(255),
                        ])
                        ->visible(fn ($record) => $record->status==PurchaseRequestsStatus::HODApproved->value &&
                            Auth::user()->can('approve_purchase::requests')
                        )
                        ->action(function (PurchaseRequests $record, array $data) {
                            $record->update([
                                'status' => PurchaseRequestsStatus::Canceled->value,
                                // 'is_canceled' => true,
                                'cancel_remark' => $data['cancel_remark'],
                                'approved_canceled_by' => Auth::id(),
                            ]);
                            $user = User::find($record->user_id);
                            Mail::to($user->email)->queue(new StatusEmail('Purchase Request '. $record->pr_no, 'canceled', $data['cancel_remark'],''));
                            Notification::make()
                                ->title('PR Canceled successfully')
                                ->warning()
                                ->send();
                        }),
                    Tables\Actions\Action::make('approve_purchase_close')
                        ->label('Close')
                        ->icon('heroicon-o-check-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Are you sure you want to close this PR? This action cannot be undone.')
                        ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::DocumentUploaded &&
                            Auth::user()->can('approve_purchase::requests')
                        )
                        ->action(function (PurchaseRequests $record) {
                          
                            $record->update([
                                'status' => PurchaseRequestsStatus::Closed->value,
                                'is_closed_by' => Auth::id(),
                            ]);
                            $po = PurchaseOrders::where('is_closed', false)->where('pr_id', $record->id)->get();
                            if(!$po){
                                foreach($po as $p){
                                    $p->update([
                                        'status' => PurchaseOrderStatus::Closed->value,
                                        'is_closed' => true,
                                        'is_closed_by' => Auth::id(),
                                    ]);
                                }
                            }
                            

                            Notification::make()
                                ->title('PR Closed successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('upload_document')
                        ->label('Upload Document')
                        ->icon('heroicon-o-document')
                        ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Approved->value && Auth::user()->can('send_approval_purchase::requests'))
                        ->form([
                            Forms\Components\FileUpload::make('uploaded_document')
                                ->label('Document')
                                ->required(),
                        ])
                        ->action(function (PurchaseRequests $record, array $data) {
                            $record->update([
                                'status' => PurchaseRequestsStatus::DocumentUploaded->value,
                                'uploaded_document' => $data['uploaded_document'],
                            ]);
                            Notification::make()
                                ->title('Document uploaded successfully')
                                ->success()
                                ->send();
                        }),
                        Tables\Actions\Action::make('view_document')
                            ->label('View Document')
                            ->icon('heroicon-o-eye')
                            ->visible(fn ($record) => $record->uploaded_document)
                            ->url(fn ($record) => asset('storage/' . $record->uploaded_document))
                            ->openUrlInNewTab(),

                    Tables\Actions\Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-eye')
                        ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Approved->value && Auth::user()->can('send_approval_purchase::requests'))
                        ->url(fn (PurchaseRequests $record) => route('purchase-requests.download', $record))
                        ->openUrlInNewTab(),
                

                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => ($record->status == PurchaseRequestsStatus::HODApproved->value &&  Auth::user()->can('approve_purchase::requests')) || ($record->status == PurchaseRequestsStatus::Draft->value && Auth::user()->can('send_approval_purchase::requests'))|| (!$record->status == PurchaseRequestsStatus::Submitted->value && Auth::user()->is_hod == true)),
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
