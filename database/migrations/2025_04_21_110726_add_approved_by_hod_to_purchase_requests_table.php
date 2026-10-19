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
        if (Schema::hasColumn('purchase_requests', 'approved_by_hod')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $column = $table->foreignId('approved_by_hod')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            if (Schema::hasColumn('purchase_requests', 'approved_canceled_by')) {
                $column->after('approved_canceled_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('purchase_requests', 'approved_by_hod')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_hod');
        });
    }
};
