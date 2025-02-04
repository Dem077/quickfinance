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
            $table->string('purpose');
            $table->string('is_closed')->default(false);
            $table->foreignId('is_closed_by')->nullable()->constrained('users');
            $table->boolean('is_submited')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_canceled_by')->nullable()->constrained('users');
            $table->boolean('is_canceled')->default(false);
            $table->text('cancel_remark')->nullable();
            $table->string('uploaded_document')->nullable();
            $table->foreignId('user_id')->constrained(); // Requested by User
            $table->foreignId(('location_id'))->constrained();
            $table->foreignId('project_id')->nullable()->constrained();
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
