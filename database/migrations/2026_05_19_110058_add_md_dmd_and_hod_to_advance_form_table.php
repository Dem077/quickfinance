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
        Schema::table('advance_forms', function (Blueprint $table) {
            $table->foreignId('md_dmd_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hod_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_forms', function (Blueprint $table) {
            $table->dropForeign(['md_dmd_approved_by']);
            $table->dropForeign(['hod_approved_by']);
        });
    }
};
