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
        $module->fields()->where('name', 'project_id')->update([
            'search' => json_encode([
                'model' => 'App\Models\Project',
                'id' => 'id',
                'text' => 'title',
            ]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'project_id')->update([
            'search' => json_encode([
                'model' => 'App\Models\Project',
                'id' => 'id',
                'text' => 'name',
            ]),
        ]);
    }
};
