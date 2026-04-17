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
        Schema::table('company_information', function (Blueprint $table) {
            $table->string('url_portal')->nullable()->after('updated_by');
        });

        $module = Module::where('name', 'CompanyInformation')->first();
        $module->fields()->create([
            'name' => 'url_portal',
            'label' => 'Enlace a Portal',
            'placeholder' => 'Enlace a Portal',
            'type' => 1,
            'additional_field'=> false,
            'position' => 15
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            $table->dropColumn('url_portal');
        });

        $module = Module::where('name', 'CompanyInformation')->first();
        $module->fields()->where('name','url_portal')->delete();
    }
};
