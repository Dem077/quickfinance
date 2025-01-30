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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_no');
            $table->date('date');
            $table->foreignId('budget_account_id')->constrained();
            $table->boolean('is_submited')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained();
            $table->boolean('is_canceled')->default(false);
            $table->text('cancel_remark')->nullable();
            $table->foreignId('user_id')->constrained(); // Requested by User
            $table->foreignId(('department_id'))->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
