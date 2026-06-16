<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_recurring_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('openpay_customer_id', 40);
            $table->string('openpay_card_id', 40);       // source_id — PAN NUNCA se persiste aquí
            $table->string('card_brand', 30)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_exp', 7)->nullable();    // formato "MM/YYYY"
            $table->string('cardholder', 120)->nullable();
            $table->enum('status', ['active', 'paused', 'failed', 'cancelled'])->default('active');
            $table->timestamp('consent_at')->nullable();
            $table->enum('consent_channel', ['portal', 'app', 'admin', 'liga'])->default('portal');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_recurring_cards');
    }
};
