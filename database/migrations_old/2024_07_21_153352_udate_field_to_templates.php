<?php

use App\Http\Controllers\Utils\ComunConstantsController;
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
        $module = Module::where('name', "Plantillas")->first();
        $module->fields()->where('name', 'template')->update([
            'options' => null,
            'search' => json_encode([
                'model' => 'App\Models\ContractTemplate',
                'id' => 'id',
                'text' => 'name'
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', "Plantillas")->first();
        $module->fields()->where('name', 'template')->update([
            'options' => json_encode(ComunConstantsController::TEMPLATE_CONTRACTS),
            'search' => null
        ]);
    }
};
