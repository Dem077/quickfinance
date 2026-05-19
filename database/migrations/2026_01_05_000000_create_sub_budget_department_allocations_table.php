<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_budget_department_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_budget_account_id')->constrained('sub_budget_accounts')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->timestamps();
            $table->unique(['sub_budget_account_id', 'department_id'], 'sbda_sub_budget_dept_unique');
        });

        // Backfill existing single-department amounts into the new allocation table.
        DB::table('sub_budget_accounts')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                $insert = [];
                foreach ($rows as $row) {
                    $insert[] = [
                        'sub_budget_account_id' => $row->id,
                        'department_id' => $row->department_id,
                        'amount' => $row->amount ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($insert) {
                    // Use insertOrIgnore to avoid collisions if rerun.
                    DB::table('sub_budget_department_allocations')->insertOrIgnore($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_budget_department_allocations');
    }
};
