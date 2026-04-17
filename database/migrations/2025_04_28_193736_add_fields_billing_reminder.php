<?php

use App\Models\Module;
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
        $module = Module::where('name', 'BillingReminder')->first();
        $field =  [
            'name' => 'cc_email',
            'label' => 'Correo electrónico CCO',
            'placeholder' => 'Correo CCO, separado por comas',
            'type' => 1,
            'position' => 20,
            'additional_field' => false,
        ];
        $module->fields()->create($field);

        Schema::table('billing_reminders', function (Blueprint $table) {
            $table->string('cc_email')->nullable()->after('attach_paid_invoices');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'BillingReminder')->first();
        $module->fields()->where('name', 'cc_email')->delete();
        Schema::table('billing_reminders', function (Blueprint $table) {
            $table->dropColumn('cc_email');
        });
    }
};
