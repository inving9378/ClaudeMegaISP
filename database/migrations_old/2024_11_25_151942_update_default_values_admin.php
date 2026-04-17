<?php

use App\Http\Repository\ModuleRepository;
use App\Models\DefaultValue;
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
        $module = Module::where('name', 'Task')->first();
        $defaultValues = DefaultValue::where('module_id', $module->id)->get();
        foreach ($defaultValues as $defaultValue) {
            $defaultValue->delete();
        }

        $module = Module::where('name', 'FiltersTaskCalendar')->first();
        $defaultValues = DefaultValue::where('module_id', $module->id)->get();
        foreach ($defaultValues as $defaultValue) {
            $defaultValue->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
