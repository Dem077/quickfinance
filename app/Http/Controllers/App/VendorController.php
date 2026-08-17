<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\App\Concerns\HandlesSimpleCrud;
use App\Http\Controllers\Controller;
use App\Models\Vendors;
use Illuminate\Database\Eloquent\Model;

class VendorController extends Controller
{
    use HandlesSimpleCrud;

    protected function modelClass(): string
    {
        return Vendors::class;
    }

    protected function inertiaPrefix(): string
    {
        return 'Vendors';
    }

    protected function routePrefix(): string
    {
        return 'app.vendors';
    }

    protected function searchableColumns(): array
    {
        return ['name', 'address', 'bank', 'account_no', 'mobile', 'gst_no'];
    }

    protected function validationRules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'bank' => ['required', 'in:BML,MIB,CBM,SBI'],
            'account_no' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:255'],
            'gst_no' => ['required', 'string', 'max:255'],
        ];
    }

    protected function transform(Model $model): array
    {
        return [
            'name' => $model->name,
            'address' => $model->address,
            'bank' => $model->bank,
            'account_no' => $model->account_no,
            'mobile' => $model->mobile,
            'gst_no' => $model->gst_no,
        ];
    }

    protected function formProps(?Model $model = null): array
    {
        return [
            'banks' => ['BML', 'MIB', 'CBM', 'SBI'],
        ];
    }
}
