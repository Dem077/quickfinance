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
        Schema::create('advance_forms', function (Blueprint $table) {
            $table->id();
            $table->string('qoation_no');
            $table->string('expected_delivery');
            $table->string('request_number'); // LADV/PROC/1126 start from here
            $table->string('advance_percentage');
            $table->string('advance_amount');
            $table->string('balance_amount');
            $table->foreignId('generated_by')->constrained('users');
            $table->foreignId('vendors_id')->constrained('vendors');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_forms');
    }
};
