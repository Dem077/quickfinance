<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_detail_id']);
            $table->dropUnique(['purchase_order_detail_id']);
        });

        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->unsignedSmallInteger('unit_index')
                ->default(1)
                ->after('purchase_order_detail_id');
            $table->unique(['purchase_order_detail_id', 'unit_index'], 'asset_receipts_po_detail_unit_unique');
            $table->foreign('purchase_order_detail_id')
                ->references('id')
                ->on('purchase_order_details')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_detail_id']);
            $table->dropUnique('asset_receipts_po_detail_unit_unique');
            $table->dropColumn('unit_index');
            $table->unique('purchase_order_detail_id');
            $table->foreign('purchase_order_detail_id')
                ->references('id')
                ->on('purchase_order_details')
                ->cascadeOnDelete();
        });
    }
};
