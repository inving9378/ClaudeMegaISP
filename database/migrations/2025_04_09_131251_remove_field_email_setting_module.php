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
        $module = Module::where('name', 'EmailSetting')->first();
        $module->fields()->where('name', 'limit_per_minute')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'EmailSetting')->first();
        $fields = [
            [
                'name' => 'limit_per_minute',
                'label' => 'Limite por Minuto',
                'placeholder' => '',
                'type' => 15,
                'position' => 10,
                'hint'=> 'Configure en 0 para envíos ilimitados.',
                'additional_field' => false,
            ],

        ];
        $module->fields()->createMany($fields);
    }
};
