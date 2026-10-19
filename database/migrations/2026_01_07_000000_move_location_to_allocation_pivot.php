<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sub_budget_department_allocations', 'location_id')) {
            Schema::table('sub_budget_department_allocations', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('department_id')->constrained('locations');
            });
        }

        if (
            Schema::hasColumn('sub_budget_department_allocations', 'location_id')
            && Schema::hasColumn('sub_budget_accounts', 'location_id')
        ) {
            DB::table('sub_budget_department_allocations as sbda')
                ->join('sub_budget_accounts as sba', 'sbda.sub_budget_account_id', '=', 'sba.id')
                ->whereNotNull('sba.location_id')
                ->update(['sbda.location_id' => DB::raw('sba.location_id')]);
        }

        if (Schema::hasColumn('sub_budget_accounts', 'location_id')) {
            $this->dropForeignKeyIfExists('sub_budget_accounts', 'location_id');

            Schema::table('sub_budget_accounts', function (Blueprint $table) {
                $table->dropColumn('location_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sub_budget_accounts', 'location_id')) {
            Schema::table('sub_budget_accounts', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->constrained('locations');
            });
        }

        if (
            Schema::hasColumn('sub_budget_accounts', 'location_id')
            && Schema::hasColumn('sub_budget_department_allocations', 'location_id')
        ) {
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
        }

        if (Schema::hasColumn('sub_budget_department_allocations', 'location_id')) {
            $this->dropForeignKeyIfExists('sub_budget_department_allocations', 'location_id');

            Schema::table('sub_budget_department_allocations', function (Blueprint $table) {
                $table->dropColumn('location_id');
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $database = Schema::getConnection()->getDatabaseName();

        $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if (! $foreignKey) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
            $blueprint->dropForeign($foreignKey);
        });
    }
};
