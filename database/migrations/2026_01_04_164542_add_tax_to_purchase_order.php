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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('grn_number')->nullable()->after('payment_method');
        });
        Schema::table('purchase_order_details', function (Blueprint $table) {
            $table->text('tax_amount')->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
            $table->dropColumn('grn_number');
        });
        Schema::table('purchase_order_details', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
};
