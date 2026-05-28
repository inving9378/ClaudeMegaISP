<?php

use App\Models\Module;
use App\Modules\Core\Configuracion\Models\FieldType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::where('name', 'Task')->first();
        if (!$module) return;

        $type = FieldType::where('name', 'select-component-client-task')->first();
        if (!$type) return;

        $module->fields()->where('name', 'client_main_information_id')->update([
            'type' => $type->id,
        ]);
    }

    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        if (!$module) return;

        $type = FieldType::where('name', 'select-component-client')->first();
        if (!$type) return;

        $module->fields()->where('name', 'client_main_information_id')->update([
            'type' => $type->id,
        ]);
    }
};
