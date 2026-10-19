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
        if (Schema::hasColumn('purchase_requests', 'approved_by_md_dmd')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $column = $table->foreignId('approved_by_md_dmd')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            if (Schema::hasColumn('purchase_requests', 'approved_by_hod')) {
                $column->after('approved_by_hod');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('purchase_requests', 'approved_by_md_dmd')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_md_dmd');
        });
    }
};
