<?php

use App\Enums\PurchaseRequestsStatus;
use App\Models\AdvanceForm;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\Route;

/* NOTE: Do Not Remove
/ Livewire asset handling if using sub folder in domain
*/
Livewire::setUpdateRoute(function ($handle) {
    return Route::post(env('ASSET_PREFIX', '').'/livewire/update', $handle);
});

Livewire::setScriptRoute(function ($handle) {
    return Route::get(env('ASSET_PREFIX', '').'/livewire/livewire.js', $handle);
});
/*
/ END
*/

Route::get('/', function () {
    return redirect('/admin');
});
Route::get('pr/{record}/preview', function (PurchaseRequests $record) {
    // Check if document is approved
    if (! $record->status == PurchaseRequestsStatus::Approved->value) {
        abort(403, 'Access denied. Document is not approved.');
    }
    if (! $record->uploaded_document == null) {
        abort(403, 'Access denied. Document is already signed and uploaded.');
    }

    $record->load(['project','locations', 'location', 'user', 'approvedby', 'hodapprovedby']);

    $items = $record->purchaseRequestDetails()->with('item')->get();

    $html = view('pdf.purchase-request', ['record' => $record, 'items' => $items])->render();

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_header' => '0',
        'margin_top' => '15',
        'margin_bottom' => '30',
        'margin_footer' => '20',
    ]);

    $mpdf->WriteHTML($html);

    return response($mpdf->Output('', \Mpdf\Output\Destination::INLINE))
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="'.$record->pr_no.'.pdf"');
})->name('purchase-requests.download');

Route::get('adv-form/{record}/download', function (PurchaseOrders $record) {
   
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_header' => '10',
        'margin_top' => '15',
        'margin_bottom' => '30',
        'margin_footer' => '50',
    ]);

    $record1 = AdvanceForm::where('id', $record->advance_form_id)->first();
    
    $record1->load(['user', 'vendor', 'purchaseOrder', 'user.department.user']);
    //    dd($record->user->hodof[0]->user);
    $html = view('pdf.purchase-order-advance-form', ['record' => $record1])->render();
    $mpdf->WriteHTML($html);

    return response($mpdf->Output('', \Mpdf\Output\Destination::INLINE))
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="advance-form.pdf"');
})->name('purchase-orders.advance-form.download');

Route::get('petty-cash/{record}/preview', function (PettyCashReimbursment $record) {
    // // Check if document is approved
    // if (!$record->is_approved) {
    //     abort(403, 'Access denied. Document is not approved.');
    // }

    $record->load(['user', 'pettyCashReimbursmentDetails', 'VerifiedBy', 'ApprovedBy']);

    $items = $record->pettyCashReimbursmentDetails()->with(['vendor', 'purchaseOrder', 'subBudget', 'pettyCashReimbursment'])->get();

    $html = view('pdf.petty-cash-form', ['record' => $record, 'items' => $items])->render();

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L',
        'margin_header' => '0',
        'margin_top' => '43',
        'margin_bottom' => '50',
        'margin_footer' => '10',
    ]);

    $mpdf->WriteHTML($html);

    return response($mpdf->Output('', \Mpdf\Output\Destination::INLINE))
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="preview.pdf"');
})->name('petty-cash.preview');

// Route::get('mail/preview', function () {
//     return new App\Mail\TestEmail();
// });
