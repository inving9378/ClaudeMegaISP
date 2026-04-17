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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id(); // ID único del recordatorio
            $table->string('client_id')->nullable();
            // Tipo de recordatorio (payment, delivery, etc.)
            $table->string('type');
            // via (email, sms, etc.)
            $table->string('via');
            // Estado del recordatorio (sent, pending, failed, etc.)
            $table->string('status')->default('pending');
            // Fecha de vencimiento del recordatorio (si aplica)
            $table->dateTime('due_date')->nullable();
            // Fecha en que se envió el recordatorio
            $table->dateTime('sent_at')->nullable();
            // Campo para almacenar el error en caso de que falle el envío
            $table->text('error_message')->nullable();
            $table->text('email_if_error')->nullable();
            // Campos adicionales para almacenar información relacionada (opcional)
            $table->string('recipient_email')->nullable(); // Para recordatorios por email
            $table->string('cc_email')->nullable();
            $table->string('recipient_phone')->nullable(); // Para recordatorios por SMS
            $table->string('subject')->nullable();
            $table->longText('html')->nullable(); // Mensaje del recordatorio

            // Timestamps para created_at y updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
