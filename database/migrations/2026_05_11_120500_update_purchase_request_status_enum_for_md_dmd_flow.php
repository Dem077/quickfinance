<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Step 1: temporarily allow both old and new enum values.
        DB::statement("
            ALTER TABLE purchase_requests
            MODIFY COLUMN status ENUM(
                'draft',
                'submitted',
                'hod_approved',
                'hod_rejected',
                'document_uploaded',
                'approved',
                'md_dmd_approved',
                'md_dmd_rejected',
                'canceled',
                'rejected',
                'closed'
            ) NOT NULL DEFAULT 'draft'
        ");

        // Step 2: migrate old values to new values.
        DB::statement("UPDATE purchase_requests SET status = 'md_dmd_approved' WHERE status = 'document_uploaded'");

        // Step 3: remove deprecated enum values.
        DB::statement("
            ALTER TABLE purchase_requests
            MODIFY COLUMN status ENUM(
                'draft',
                'submitted',
                'hod_approved',
                'hod_rejected',
                'approved',
                'md_dmd_approved',
                'md_dmd_rejected',
                'canceled',
                'rejected',
                'closed'
            ) NOT NULL DEFAULT 'draft'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Step 1: temporarily allow both old and new enum values.
        DB::statement("
            ALTER TABLE purchase_requests
            MODIFY COLUMN status ENUM(
                'draft',
                'submitted',
                'hod_approved',
                'hod_rejected',
                'document_uploaded',
                'approved',
                'md_dmd_approved',
                'md_dmd_rejected',
                'canceled',
                'rejected',
                'closed'
            ) NOT NULL DEFAULT 'draft'
        ");

        // Step 2: migrate new values back to old values.
        DB::statement("UPDATE purchase_requests SET status = 'document_uploaded' WHERE status = 'md_dmd_approved'");

        // Step 3: remove md/dmd enum values.
        DB::statement("
            ALTER TABLE purchase_requests
            MODIFY COLUMN status ENUM(
                'draft',
                'submitted',
                'hod_approved',
                'hod_rejected',
                'document_uploaded',
                'approved',
                'canceled',
                'rejected',
                'closed'
            ) NOT NULL DEFAULT 'draft'
        ");
    }
};
