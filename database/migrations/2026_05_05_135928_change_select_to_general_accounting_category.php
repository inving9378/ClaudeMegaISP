<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::where('name', 'GeneralAccountingCategory')->first();
        if ($module) {
            $module->fields()
                ->where('name', 'type_id')
                ->update(['type' => 23]);
        }
    }

    public function down(): void
    {
        $module = Module::where('name', 'GeneralAccountingCategory')->first();
        if ($module) {
            $module->fields()
                ->where('name', 'type_id')
                ->update(['type' => 22]);
        }
    }
};
