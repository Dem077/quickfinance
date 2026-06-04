<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('snipe_category_id')->nullable()->after('snipe_supplier_id');
            $table->unsignedInteger('snipe_quantity')->nullable()->after('snipe_category_id');
            $table->unsignedBigInteger('snipe_it_accessory_id')->nullable()->after('snipe_it_hardware_id');
        });
    }

    public function down(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'snipe_category_id',
                'snipe_quantity',
                'snipe_it_accessory_id',
            ]);
        });
    }
};
