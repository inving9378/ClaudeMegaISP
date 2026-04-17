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
        Schema::create('payment_emails', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->nullable();
            // via (email, sms, etc.)
            $table->string('via');
            // Estado del recordatorio (sent, pending, failed, etc.)
            $table->string('status')->default('pending');
            // Fecha de vencimiento
            $table->dateTime('due_date')->nullable();
            // Fecha en que se envió el coreo
            $table->dateTime('sent_at')->nullable();
            // Campo para almacenar el error en caso de que falle el envío
            $table->text('error_message')->nullable();
            $table->text('email_if_error')->nullable();
            // Campos adicionales para almacenar información relacionada (opcional)
            $table->string('recipient_email')->nullable(); // Para email
            $table->string('cc_email')->nullable();
            $table->string('recipient_phone')->nullable(); // Para  SMS
            $table->string('subject')->nullable();
            $table->longText('html')->nullable(); // Mensaj
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_emails');
    }
};
