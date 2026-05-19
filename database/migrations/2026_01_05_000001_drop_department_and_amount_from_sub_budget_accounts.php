<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'sub_budget_accounts';
        $database = Schema::getConnection()->getDatabaseName();

        if (Schema::hasColumn($table, 'department_id')) {
            $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', 'department_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');

            if ($foreignKey) {
                Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                    $blueprint->dropForeign($foreignKey);
                });
            } else {
                $index = DB::table('information_schema.STATISTICS')
                    ->where('TABLE_SCHEMA', $database)
                    ->where('TABLE_NAME', $table)
                    ->where('COLUMN_NAME', 'department_id')
                    ->where('INDEX_NAME', '!=', 'PRIMARY')
                    ->value('INDEX_NAME');

                if ($index) {
                    Schema::table($table, function (Blueprint $blueprint) use ($index) {
                        $blueprint->dropIndex($index);
                    });
                }
            }
        }

        $columns = collect(['department_id', 'amount'])
            ->filter(fn (string $column) => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        if ($columns !== []) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('sub_budget_accounts', function (Blueprint $table) {
            $table->integer('amount')->default(0);
            $table->foreignId('department_id')->nullable()->constrained();
        });
    }
};
