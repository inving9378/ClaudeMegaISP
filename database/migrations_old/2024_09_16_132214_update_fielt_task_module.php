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
        $module->fields()->where('name', 'template_id')->update([
            'search' => json_encode([
                'model' => 'App\Models\TemplateTask',
                'id' => 'id',
                'text' => 'title_template',
            ]),
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'template_id')->update([
            'search' => json_encode([
                'model' => 'App\Models\DocumentTemplate',
                'id' => 'id',
                'text' => 'name',
            ]),
        ]);
    }
};
