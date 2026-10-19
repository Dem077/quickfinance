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
        if (Schema::hasColumn('petty_cash_reimbursments', 'form_no')) {
            return;
        }

        Schema::table('petty_cash_reimbursments', function (Blueprint $table) {
            $table->string('form_no')->nullable()->after('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('petty_cash_reimbursments', 'form_no')) {
            Schema::table('petty_cash_reimbursments', function (Blueprint $table) {
                $table->dropColumn('form_no');
            });
        }
    }
};
