<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->string('asset_tag')->nullable()->after('status');
            $table->string('name')->nullable()->after('asset_tag');
            $table->unsignedBigInteger('snipe_model_id')->nullable()->after('serial_number');
            $table->unsignedBigInteger('snipe_status_id')->nullable()->after('snipe_model_id');
            $table->unsignedBigInteger('snipe_location_id')->nullable()->after('snipe_status_id');
            $table->unsignedBigInteger('snipe_supplier_id')->nullable()->after('snipe_location_id');
            $table->date('purchase_date')->nullable()->after('invoice_number');
            $table->decimal('purchase_cost', 15, 2)->nullable()->after('purchase_date');
            $table->text('notes')->nullable()->after('purchase_cost');
        });

        foreach (DB::table('asset_receipts')->whereNotNull('asset_description')->whereNull('name')->get(['id', 'asset_description']) as $row) {
            DB::table('asset_receipts')->where('id', $row->id)->update(['name' => $row->asset_description]);
        }
    }

    public function down(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'asset_tag',
                'name',
                'snipe_model_id',
                'snipe_status_id',
                'snipe_location_id',
                'snipe_supplier_id',
                'purchase_date',
                'purchase_cost',
                'notes',
            ]);
        });
    }
};
