<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_requests', 'status')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->enum('status', [
                    'draft',
                    'submitted',
                    'hod_approved',
                    'hod_rejected',
                    'document_uploaded',
                    'canceled',
                    'approved',
                    'closed',
                ])->default('draft')->after('id');
            });
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE purchase_requests
                MODIFY COLUMN status ENUM(
                    'draft',
                    'submitted',
                    'hod_approved',
                    'hod_rejected',
                    'document_uploaded',
                    'canceled',
                    'approved',
                    'closed'
                ) NOT NULL DEFAULT 'draft'
            ");
        }

        if (! Schema::hasColumn('purchase_orders', 'status')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->enum('status', [
                    'submitted',
                    'closed',
                    'reimbursed',
                    'reimbursement_pending',
                    'grn_created',
                    'draft',
                ])->default('draft')->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Status columns are introduced by earlier migrations; leave them in place.
    }
};
