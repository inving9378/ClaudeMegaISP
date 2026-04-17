<?php

use App\Models\FieldType;
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
        $newFieldType = FieldType::create([
            'name' => 'input-file-imagen'
        ]);
        $module = Module::where('name', 'CompanyInformation')->first();
        $module->fields()->where('name', 'logo')->first()->update(['type' => $newFieldType->id]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'CompanyInformation')->first();
        $module->fields()->where('name', 'logo')->first()->update(['type' => 6]);
        $fliedType = FieldType::where('name', 'input-file-imagen')->first();
        $fliedType->delete();
    }
};
