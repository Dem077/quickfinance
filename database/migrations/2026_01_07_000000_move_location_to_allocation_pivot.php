<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add location_id to allocations pivot table
        Schema::table('sub_budget_department_allocations', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('department_id')->constrained('locations');
        });

        // Backfill location_id from sub_budget_accounts to allocations
        DB::table('sub_budget_department_allocations as sbda')
            ->join('sub_budget_accounts as sba', 'sbda.sub_budget_account_id', '=', 'sba.id')
            ->whereNotNull('sba.location_id')
            ->update(['sbda.location_id' => DB::raw('sba.location_id')]);

        // Drop location_id from sub_budget_accounts
        Schema::table('sub_budget_accounts', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }

    public function down(): void
    {
        // Re-add location_id to sub_budget_accounts
        Schema::table('sub_budget_accounts', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained('locations');
        });

        // Backfill from first allocation (if multiple exist, picks first)
        DB::statement('
            UPDATE sub_budget_accounts sba
            JOIN (
                SELECT sub_budget_account_id, MIN(location_id) as location_id
                FROM sub_budget_department_allocations
                WHERE location_id IS NOT NULL
                GROUP BY sub_budget_account_id
            ) sbda ON sba.id = sbda.sub_budget_account_id
            SET sba.location_id = sbda.location_id
        ');

        // Drop location_id from allocations
        Schema::table('sub_budget_department_allocations', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
