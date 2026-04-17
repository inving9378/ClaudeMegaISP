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
        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->where('name', 'title')->first()->update([
            'class_label' => "col-form-label pr-2",
            'class_field' => "col-sm-12"
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->where('name', 'title')->first()->update([
            'class_label' => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
            'class_field' => "col-sm-12 col-md-9"
        ]);
    }
};
