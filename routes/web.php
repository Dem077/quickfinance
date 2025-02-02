<?php

use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\Route;
use Mpdf\Tag\Pre;

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
    return view('welcome');
});
Route::get('purchase-requests/{record}/preview', function ( PurchaseRequests $record ) {
    // Check if document is approved
    if (!$record->is_approved) {
        abort(403, 'Access denied. Document is not approved.');
    } 
    if( !$record->uploaded_document == null ) {
        abort(403, 'Access denied. Document is already signed and uploaded.');
    }

    $record->load(['project','location', 'budgetAccount', 'user' , 'approvedby']);

    $items = $record->purchaseRequestDetails()->get();
    $html = view('pdf.purchase-request', ['record' => $record , 'items' => $items])->render();
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_header' => '0',
        'margin_top' => '15',
        'margin_bottom' => '30',
        'margin_footer' => '10',
    ]);
 

    $mpdf->WriteHTML($html);
    
    return response($mpdf->Output('', \Mpdf\Output\Destination::INLINE))
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="preview.pdf"');
})->name('purchase-requests.preview');

Route::get('purchase-orders.advance-form.download', function () {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_header' => '0',
        'margin_top' => '15',
        'margin_bottom' => '30',
        'margin_footer' => '10',
    ]);
    $html = view('pdf.purchase-order-advance-form')->render();
    $mpdf->WriteHTML($html);
    
    return response($mpdf->Output('', \Mpdf\Output\Destination::INLINE))
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="advance-form.pdf"');
})->name('purchase-orders.advance-form.download');