<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('petty_cash_reimbursments', 'budget_deducted_at')) {
            return;
        }

        Schema::table('petty_cash_reimbursments', function (Blueprint $table) {
            $column = $table->timestamp('budget_deducted_at')->nullable();

            if (Schema::hasColumn('petty_cash_reimbursments', 'approved_by')) {
                $column->after('approved_by');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('petty_cash_reimbursments', 'budget_deducted_at')) {
            Schema::table('petty_cash_reimbursments', function (Blueprint $table) {
                $table->dropColumn('budget_deducted_at');
            });
        }
    }
};
