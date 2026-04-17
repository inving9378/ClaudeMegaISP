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
        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $fieldsLeft = [
            [
                'name' => 'title',
                'label' => 'Título',
                'placeholder' => 'Título',
                'type' => 1,
                'position' => 0,
                'additional_field' => false,
            ],
        ];
        $moduleLeft->fields()->createMany($fieldsLeft);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleLeft = Module::where('name', 'TaskLeft')->first();

        $moduleLeft->fields()->where('name', 'title')->delete();
    }
};
