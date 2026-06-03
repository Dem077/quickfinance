<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_reimbursments', function (Blueprint $table) {
            $table->timestamp('budget_deducted_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_reimbursments', function (Blueprint $table) {
            $table->dropColumn('budget_deducted_at');
        });
    }
};
