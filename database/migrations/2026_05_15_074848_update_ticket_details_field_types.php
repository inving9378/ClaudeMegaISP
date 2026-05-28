<?php

use App\Models\Module;
use App\Modules\Core\Configuracion\Models\FieldType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::where('name', 'TicketDetails')->first();
        if (!$module) return;

        $prospectType = FieldType::where('name', 'select-component-client-prospect')->first();
        if ($prospectType) {
            $module->fields()->where('name', 'customer_lead')->update([
                'type' => $prospectType->id,
            ]);
        }

        $longType = FieldType::where('name', 'select-long-component')->first();
        if ($longType) {
            $module->fields()->where('name', 'colony_id')->update([
                'type' => $longType->id,
            ]);
        }
    }

    public function down(): void
    {
        $module = Module::where('name', 'TicketDetails')->first();
        if (!$module) return;

        $defaultType = FieldType::where('name', 'select-component')->first();
        if (!$defaultType) return;

        $module->fields()->where('name', 'customer_lead')->update([
            'type' => $defaultType->id,
        ]);

        $module->fields()->where('name', 'colony_id')->update([
            'type' => $defaultType->id,
        ]);
    }
};
