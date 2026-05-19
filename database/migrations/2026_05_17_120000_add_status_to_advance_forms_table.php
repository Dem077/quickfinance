<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advance_forms', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('vendors_id');
        });

        DB::table('advance_forms')->whereNull('status')->update(['status' => 'dmd_md_approved']);
    }

    public function down(): void
    {
        Schema::table('advance_forms', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
