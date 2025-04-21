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
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'submitted',
                'hod_approved',
                'hod_rejected',
                'document_uploaded',
                'canceled',
                'approved',
                'closed'
            ])->default('draft')->after('id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('status', [
                'submitted',
                'closed',
                'reimbursed',
                'draft'
            ])->default('draft')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
