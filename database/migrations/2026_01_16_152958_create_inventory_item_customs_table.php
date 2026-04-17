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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('inventory_item_custom_model_id')
                ->nullable()
                ->constrained('inventory_item_custom_models');

            $table->index('serial_number');
            $table->index('serial_number_enable');
        });

        $module = Module::create([
            'name' => 'InventoryItemCustom',
            'group' => 'Administration'
        ]);
        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_custom_model_id']);
            $table->dropColumn('inventory_item_custom_model_id');
            $table->dropIndex(['serial_number']);
            $table->dropIndex(['serial_number_enable']);
        });
        $module = Module::where('name','InventoryItemCustom')->first();
        $module->packages()->detach();        
        $module->delete();
    }
};
