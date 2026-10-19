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
        if (! Schema::hasColumn('advance_forms', 'md_dmd_approved_by')) {
            Schema::table('advance_forms', function (Blueprint $table) {
                $table->foreignId('md_dmd_approved_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('advance_forms', 'hod_approved_by')) {
            Schema::table('advance_forms', function (Blueprint $table) {
                $table->foreignId('hod_approved_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('advance_forms', 'md_dmd_approved_by')) {
            Schema::table('advance_forms', function (Blueprint $table) {
                $table->dropConstrainedForeignId('md_dmd_approved_by');
            });
        }

        if (Schema::hasColumn('advance_forms', 'hod_approved_by')) {
            Schema::table('advance_forms', function (Blueprint $table) {
                $table->dropConstrainedForeignId('hod_approved_by');
            });
        }
    }
};
