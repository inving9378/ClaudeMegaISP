<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('parental_accounts')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('parental_plans');
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'plan_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void { Schema::dropIfExists('parental_licenses'); }
};
