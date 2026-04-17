<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->boolean('is_dedicated')->default(false)->after('last_time_online');
        });

        $module = Module::where('name', 'ClientAdditionalInformation')->first();
        $module->fields()->create([
            'name'             => 'is_dedicated',
            'label'            => 'Cliente Dedicado',
            'type'             => 16,
            'position'         => 98,
            'additional_field' => false,
            'disabled'         => 0,
            'default_value'    => 'false',
        ]);
    }

    public function down(): void
    {
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->dropColumn('is_dedicated');
        });

        $module = Module::where('name', 'ClientAdditionalInformation')->first();
        $module->fields()->where('name', 'is_dedicated')->delete();
    }
};