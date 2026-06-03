<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->string('cao_asset_code')->nullable()->after('notes');
            $table->string('finance_old_asset_tag')->nullable()->after('cao_asset_code');
            $table->string('asset_class')->nullable()->after('finance_old_asset_tag');
            $table->string('mac_address')->nullable()->after('asset_class');
        });
    }

    public function down(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'cao_asset_code',
                'finance_old_asset_tag',
                'asset_class',
                'mac_address',
            ]);
        });
    }
};
