<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->index();
            $table->string('mailable')->nullable()->index();
            $table->string('subject')->nullable();
            $table->json('recipients')->nullable();
            $table->string('queue_connection')->nullable();
            $table->string('queue_name')->nullable();
            $table->string('queue_job_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
