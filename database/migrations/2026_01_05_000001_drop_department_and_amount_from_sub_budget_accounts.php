<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_budget_accounts', function (Blueprint $table) {
            // Drop FK before dropping the column to avoid constraint errors.
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::table('sub_budget_accounts', function (Blueprint $table) {
            $table->integer('amount')->default(0);
            $table->foreignId('department_id')->nullable()->constrained();
        });
    }
};
