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
        $fieldType = FieldType::create([
            'name' => 'span-input'
        ]);

        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->where('name', 'activation_date')->first()->update(['type' => $fieldType->id]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->where('name', 'activation_date')->first()->update(['type' => 20]);
        FieldType::where('name', 'span-input')->first()->delete();
    }
};
