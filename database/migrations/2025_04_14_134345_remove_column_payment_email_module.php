<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('name', 'PaymentEmail')->first();
        $module->columnsDatatable()->where('name', 'type')->delete();

        $module = Module::where('name', 'InvoiceEmail')->first();
        $module->columnsDatatable()->where('name', 'type')->delete();

        $module = Module::create([
            'name' => 'Inbox',
            'is_main' => true,
            'group' => 'Configuration'
        ]);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $ckeditor = [23];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Inbox')->first();
        $module->packages()->detach();
        $module->delete();
    }
};
