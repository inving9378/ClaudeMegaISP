<?php

use App\Models\ConfigFinanceNotification;
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

        Schema::dropIfExists('config_finance_notifications');
        Schema::create('config_finance_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('type_config')->unique();
            $table->boolean('auto_send_notifications')->default(false);
            $table->enum('message_type', ['email', 'sms'])->default('email'); // email, SMS, etc.
            $table->string('email_template_id')->nullable();
            $table->string('sms_template')->nullable();
            $table->text('email_bcc')->nullable();
            $table->unsignedInteger('delay_hours')->default(0);
            $table->string('notification_days')->nullable();
            $table->string('notification_hours')->nullable();
            $table->boolean('attach_receipt')->default(false);
            $table->boolean('attach_invoice')->default(false);
            $table->timestamps();
        });

        ConfigFinanceNotification::create([
            'group' => 'Global',
            'type_config' => 'invoice',
            'auto_send_notifications' => false,
            'message_type' => 'email',
            'email_template_id' => 1,
            'sms_template' => null,
            'email_bcc' => null,
            'delay_hours' => 0,
            'notification_days' => '1,2',
            'notification_hours' => "12:00,12:30,13:00,13:30",
            'attach_receipt' => false,
            'attach_invoice' => false
        ]);

        ConfigFinanceNotification::create([
            'group' => 'Global',
            'type_config' => 'proforma_invoice',
            'auto_send_notifications' => false,
            'message_type' => 'email',
            'email_template_id' => 1,
            'sms_template' => null,
            'email_bcc' => null,
            'delay_hours' => 0,
            'notification_days' => '1,2',
            'notification_hours' => "12:00,12:30,13:00,13:30",
            'attach_receipt' => false,
            'attach_invoice' => false
        ]);
        ConfigFinanceNotification::create([
            'group' => 'Global',
            'type_config' => 'payment',
            'auto_send_notifications' => false,
            'message_type' => 'email',
            'email_template_id' => 3,
            'sms_template' => null,
            'email_bcc' => null,
            'delay_hours' => 0,
            'notification_days' => '1,2',
            'notification_hours' => '12:00, 12:30, 13:00, 13:30',
            'attach_receipt' => false,
            'attach_invoice' => false
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_finance_notifications');
    }
};
