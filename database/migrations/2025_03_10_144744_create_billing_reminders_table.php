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
        Schema::create('billing_reminders', function (Blueprint $table) {
            $table->id();
            $table->boolean('enable_reminders')->default(true);
            $table->enum('message_type', ['email', 'sms'])->default('email'); // email, SMS, etc.
            $table->time('send_time')->default('10:00:00');

            // Primer recordatorio
            $table->integer('reminder_1_days')->nullable();
            $table->string('reminder_1_subject')->nullable();
            $table->string('reminder_1_email_template')->nullable();
            $table->string('reminder_1_sms_template')->nullable();

            // Segundo recordatorio
            $table->integer('reminder_2_days')->nullable();
            $table->string('reminder_2_subject')->nullable();
            $table->string('reminder_2_email_template')->nullable();
            $table->string('reminder_2_sms_template')->nullable();

            // Tercer recordatorio
            $table->integer('reminder_3_days')->nullable();
            $table->string('reminder_3_subject')->nullable();
            $table->string('reminder_3_email_template')->nullable();
            $table->string('reminder_3_sms_template')->nullable();

            // Métodos de pago
            $table->boolean('all_payment_methods')->default(true);
            $table->string('payment_reminder_methods')->nullable();

            // Adjuntar facturas
            $table->boolean('attach_paid_invoices')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_reminders');
    }
};
