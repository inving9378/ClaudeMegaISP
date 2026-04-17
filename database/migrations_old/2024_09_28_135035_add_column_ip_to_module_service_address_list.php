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
        $module = Module::where('name', 'ServiceInAddressList')->first();
        $columnsDatatableByModule = [
            [
                'name' => 'ip',
                'filter_name' => null,
                'label' => "IP",
                'order' => 4
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ServiceInAddressList')->first();
        $module->columnsDatatable()->where('name', 'ip')->delete();
    }
};
