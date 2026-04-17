<?php

use App\Models\FieldType;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function PHPSTORM_META\type;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fieldType = FieldType::create([
            'name' => 'input-editor'
        ]);

        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->where('name', 'observation')->update([
            'type' => $fieldType->id
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        FieldType::where('name', 'input-editor')->delete();
        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->where('name', 'observation')->update([
            'type' => 5
        ]);
    }
};
