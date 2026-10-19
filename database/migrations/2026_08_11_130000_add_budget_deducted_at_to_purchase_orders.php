<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'is_submitted')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->boolean('is_submitted')->default(false);
            });
        }

        if (! Schema::hasColumn('purchase_orders', 'is_reimbursed')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->boolean('is_reimbursed')->default(false);
            });
        }

        if (! Schema::hasColumn('purchase_orders', 'budget_deducted_at')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $column = $table->timestamp('budget_deducted_at')->nullable();

                if (Schema::hasColumn('purchase_orders', 'is_reimbursed')) {
                    $column->after('is_reimbursed');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'budget_deducted_at')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->dropColumn('budget_deducted_at');
            });
        }
    }
};
