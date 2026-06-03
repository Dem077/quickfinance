<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_order_detail_id')->constrained('purchase_order_details')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('status')->default('pending');
            $table->text('asset_description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('model')->nullable();
            $table->string('model_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique('purchase_order_detail_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_receipts');
    }
};
