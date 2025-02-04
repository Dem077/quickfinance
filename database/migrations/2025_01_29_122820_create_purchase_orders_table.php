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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained();
            $table->string('po_no');
            $table->string('payment_method');
            $table->boolean('is_submitted')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->string('supporting_document')->nullable();
            $table->boolean('is_advance_form_required')->default(0);
            $table->foreignId('advance_form_id')->nullable()->constrained('advance_forms')->cascadeOnDelete();
            $table->foreignId('is_closed_by')->nullable()->constrained('users');
            $table->date('date');
            $table->foreignId('pr_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
