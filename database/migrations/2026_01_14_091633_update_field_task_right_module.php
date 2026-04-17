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
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'client_main_information_id')->update([
            'type' => 46,
            'search' => json_encode([
                "model" => "App\\Models\\ClientMainInformation",
                "id" => "client_id",
                "text" => "name",
            ])
        ]);
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'client_main_information_id')->update([
            'type' => 34,
            'search' => json_encode([
                "model" => "App\\Models\\ClientMainInformation",
                "id" => "id",
                "text" => "name",
                "append" => "client_name_with_fathers_names"
            ])
        ]);
    }
};
