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
        $module = Module::where('name', 'ClientInternetService')->first();
        $module->columnsDatatable()->where('name', 'client_name')->first()->update(['label' => 'Usuario secret']);
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'service_user_name')->first()->update(['label' => 'Usuario secret']);


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ClientInternetService')->first();
        $module->columnsDatatable()->where('name', 'client_name')->first()->update(['label' => 'Usuario']);

        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'service_user_name')->first()->update(['label' => 'Usuario internet']);

    }
};
