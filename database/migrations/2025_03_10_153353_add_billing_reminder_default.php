<?php

use App\Models\BillingReminder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        BillingReminder::create([
            'enable_reminders' => 1,
            'message_type' => 'email',
            'send_time' => '10:00:00',
            'reminder_1_days' => 1,
            'reminder_1_subject' => 'Recordatorio 1',
            'reminder_1_email_template' => '',
            'reminder_1_sms_template' => '',
            'reminder_2_days' => 1,
            'reminder_2_subject' => 'Recordatorio 2',
            'reminder_2_email_template' => '',
            'reminder_2_sms_template' => '',
            'reminder_3_days' => 1,
            'reminder_3_subject' => 'Recordatorio 3',
            'reminder_3_email_template' => '',
            'reminder_3_sms_template' => '',
            'all_payment_methods' => 1,
            'attach_paid_invoices' => 1
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $billingReminder = BillingReminder::first();
        $billingReminder->delete();
        DB::table('billing_reminders')->truncate();
    }
};
