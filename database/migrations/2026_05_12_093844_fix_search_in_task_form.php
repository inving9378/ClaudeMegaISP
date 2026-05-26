<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::where('name', 'Task')->first();

        if (!$module) return;

        $module->fields()->where('name', 'client_main_information_id')->update([
            'search' => json_encode([
                "model" => "App\\Models\\ClientMainInformation",
                "id"    => "id",
                "text"  => "name",
                "append" => "client_name_with_fathers_names",
            ])
        ]);
    }

    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();

        if (!$module) return;

        $module->fields()->where('name', 'client_main_information_id')->update([
            'search' => json_encode([
                "model" => "App\\Models\\ClientMainInformation",
                "id"    => "client_id",
                "text"  => "name",
            ])
        ]);
    }
};
