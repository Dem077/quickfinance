<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'grn_number')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->text('grn_number')->nullable()->after('payment_method');
            });
        }

        if (! Schema::hasColumn('purchase_order_details', 'tax_amount')) {
            Schema::table('purchase_order_details', function (Blueprint $table) {
                $table->text('tax_amount')->nullable()->after('unit_price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'grn_number')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('grn_number');
            });
        }

        if (Schema::hasColumn('purchase_order_details', 'tax_amount')) {
            Schema::table('purchase_order_details', function (Blueprint $table) {
                $table->dropColumn('tax_amount');
            });
        }
    }
};
