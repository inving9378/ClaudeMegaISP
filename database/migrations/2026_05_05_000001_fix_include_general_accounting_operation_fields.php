<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::where('name', 'GeneralAccountingOperation')->first();
        if ($module) {
            $module->fields()->update(['include' => true]);
            $module->fields()
                ->where('name', 'general_accounting_category_id')
                ->update(['type' => 23]);
        }
    }

    public function down(): void
    {
        $module = Module::where('name', 'GeneralAccountingOperation')->first();
        if ($module) {
            $module->fields()->update(['include' => false]);
            $module->fields()
                ->where('name', 'general_accounting_category_id')
                ->update(['type' => 22]);
        }
    }
};
