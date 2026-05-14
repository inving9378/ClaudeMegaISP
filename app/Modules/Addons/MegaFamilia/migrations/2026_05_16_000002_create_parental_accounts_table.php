<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('parental_plans');
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->timestamp('licensed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'plan_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void { Schema::dropIfExists('parental_accounts'); }
};
