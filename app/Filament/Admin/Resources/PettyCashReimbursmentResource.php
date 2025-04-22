<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PettyCashStatus;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PettyCashReimbursmentResource\Pages;
use App\Filament\Admin\Resources\PettyCashReimbursmentResource\RelationManagers\PettyCashReimbursmentDetailRelationManager;
use App\Mail\NotificationEmail;
use App\Mail\StatusEmail;
use App\Models\BudgetTransactionHistory;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseOrders;
use App\Models\SubBudgetAccounts;
use App\Models\User;
use App\Models\Vendors;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class PettyCashReimbursmentResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = PettyCashReimbursment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'pv_approve',
            'fin_hod_approve',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->disabled(fn ($record) => $record && $record->status!== PettyCashStatus::Draft->value )
                    ->required(),
                Forms\Components\TextInput::make('form_no')
                    ->label('Form Number')
                    ->disabled(fn ($record) =>$record && $record->status!== PettyCashStatus::Draft->value )
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
                                    ->columns(13)
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
                                            ->native(false)
                                            ->columnSpan(3)
                                            ->nullable(),
                                        Forms\Components\Select::make('po_id')
                                            ->label('Record ID')
                                            ->options(
                                                PurchaseOrders::where('status', PurchaseOrderStatus::Closed->value)
                                                    ->where('payment_method', 'petty_cash')
                                                    ->where('is_reimbursed', false)
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
                    ])->hidden(fn (string $operation): bool => $operation === 'edit' || $operation === 'view'),
                        
            ]);
            
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Reqeust ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('form_no')
                    ->label('Form Number')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->sortable(),
                    Tables\Columns\TextColumn::make('pv_number')
                    ->label('PV Numbers')
                    ->formatStateUsing(function ($state) {
                        $decoded = json_decode($state, true);
                        if (is_array($decoded)) {
                            // If it's an array of arrays with a "pv_number" key:
                            if (isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('pv_number', $decoded[0])) {
                                return implode(', ', array_column($decoded, 'pv_number'));
                            }
                            // Otherwise, assume it's a simple array.
                            return implode(', ', $decoded);
                        }
                        return $state;
                    }),
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
                    ->action(function ($record) { 
                        $record->update([
                            'status' => PettyCashStatus::Submitted
                        ]);
                        $useremail = $record->user->department->user->email;

                        Mail::to($useremail)->queue(new NotificationEmail('Petty Cash Request '. $record->id));
                    }),
                Tables\Actions\Action::make('dep_approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::Submitted->value && $record->user->id==Auth::user()->department->hod)
                    ->action(function ($record) {
                        $record->update([
                            'status' => PettyCashStatus::DepApproved
                        ]);

                        $useremail = $record->user->email;

                        Mail::to($useremail)->queue(new StatusEmail('Petty Cash Request '. $record->id , 'approved','','Department HOD'));
                        
                    }),
                Tables\Actions\Action::make('dep_reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::Submitted->value && $record->user->id==Auth::user()->department->hod)
                    ->action(function ($record) { 
                        $record->update([
                            'status' => PettyCashStatus::Dep_Reject
                        ]);

                        $useremail = $record->user->email;

                        Mail::to($useremail)->queue(new StatusEmail('Petty Cash Request '. $record->id , 'rejected','','Department HOD'));
                        
                        $pv_approvers = User::permission('pv_approve_petty::cash::reimbursment')->get();
                        foreach($pv_approvers as $approver){
                            Mail::to($approver->email)->queue(new NotificationEmail('Petty Cash Request '. $record->id));
                        }
                    }),
                Tables\Actions\Action::make('fin_approve')
                    ->label('Add PV')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::DepApproved->value && Auth::user()->can('pv_approve_petty::cash::reimbursment'))
                    ->form([
                        
                        Forms\Components\Repeater::make('pv_numbers')
                            ->label('PV Numbers')
                            ->simple(
                                Forms\Components\TextInput::make('pv_number')
                                    ->required(),
                            )
                    ])
                    ->action(function ($record, array $data) { 
                       
                        $record->update([
                            'status' => PettyCashStatus::FinApproved,
                            'pv_number' => json_encode($data['pv_numbers']),
                            'verified_by' => Auth::id(),
                        ]);

                        $fin_hod_approvers = User::permission('fin_hod_approve_petty::cash::reimbursment')->get();

                        foreach($fin_hod_approvers as $approver){
                            Mail::to($approver->email)->queue(new NotificationEmail('Petty Cash Request '. $record->id));
                        }

                    }),
                Tables\Actions\Action::make('fin_reject')
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::DepApproved->value && Auth::user()->can('pv_approve_petty::cash::reimbursment'))
                    ->action(function ($record) { 
                        $record->update([
                            'status' => PettyCashStatus::Fin_Reject
                        ]);

                        $useremail = $record->user->email;

                        Mail::to($useremail)->queue(new StatusEmail('Petty Cash Request '. $record->id , 'rejected','','Finance'));
                    }),

                Tables\Actions\Action::make('rembursed')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::FinApproved->value && Auth::user()->can('fin_hod_approve_petty::cash::reimbursment'))
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => PettyCashStatus::Rembursed,
                            'approved_by' => Auth::id(),
                        ]);
                        foreach($record->pettyCashReimbursmentDetails as $detail){

                            $detail->subBudget->update([
                                'amount' => $detail->subBudget->amount - $detail->amount,
                            ]);

                            BudgetTransactionHistory::createtransaction(
                                $detail->sub_budget_id,
                                'Petty Cash Reimbursement',
                                $detail->amount,
                                SubBudgetAccounts::find($detail->sub_budget_id)->amount,
                                'Petty Cash Reimbursement for Request ID '.$record->id,
                                Auth::id()
                            );
                            if($detail->po_id != null){
                                PurchaseOrders::find($detail->po_id)->update(['is_reimbursed' => true]);
                            }
                        }
                        $useremail = $record->user->email;
                        Mail::to($useremail)->queue(new StatusEmail('Petty Cash Request '. $record->id , 'approved','','Finance'));
                        
                }),
                
                Tables\Actions\Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-eye')
                        ->visible(fn ($record) => $record->status->value === PettyCashStatus::Rembursed->value)
                        ->url(fn (PettyCashReimbursment $record) => route('petty-cash.preview', $record))
                        ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('send_back')
                    ->label('Reject')
                    ->tooltip('Send back to payables')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::FinApproved->value && Auth::user()->can('fin_hod_approve_petty::cash::reimbursment'))
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => PettyCashStatus::DepApproved,
                            'pv_number' => null,
                        ]);
                        $verifiedby = $record->VerifiedBy->email;

                        Mail::to($verifiedby)->queue(new StatusEmail('Petty Cash Request '. $record->id , 'rejected','','Finance'));

                        $useremail = $record->user->email;

                        Mail::to($useremail)->queue(new StatusEmail('Petty Cash Request '. $record->id , 'rejected','','Finance'));
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status->value === PettyCashStatus::Draft->value || $record->status->value === PettyCashStatus::FinApproved->value),
               
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
            PettyCashReimbursmentDetailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPettyCashReimbursments::route('/'),
            'create' => Pages\CreatePettyCashReimbursment::route('/create'),
            'view' => Pages\ViewPettyCashReimbursment::route('/{record}/view'),
            'edit' => Pages\EditPettyCashReimbursment::route('/{record}/edit'),
            
        ];
    }
}
