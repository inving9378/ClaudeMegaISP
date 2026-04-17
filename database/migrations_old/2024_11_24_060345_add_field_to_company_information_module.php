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
            $table->string('logo')->nullable()->after('updated_by');
            $table->string('url_logo')->nullable()->after('logo');
        });

        $module = Module::where('name', 'CompanyInformation')->first();
        $module->fields()->create([
            'name' => 'logo',
            'label' => 'Logo',
            'placeholder' => 'Logo',
            'type' => 6,
            'additional_field'=> false,
            'position' => 800
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            $table->dropColumn('logo');
            $table->dropColumn('url_logo');
         });
        $module = Module::where('name', 'CompanyInformation')->first();
        $module->fields()->where('name', 'logo')->first()->delete();
    }
};
