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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('color')->after('name')->nullable();
        });
        $module = Module::where('name', 'Team')->first();
        $fields = [
            [
                'name' => 'color',
                'label' => 'Color',
                'placeholder' => 'Color',
                'type' => 40,
                'position' => 3,
                'additional_field' => false,
            ],
        ];
        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('color');
        });
        $module = Module::where('name', 'Team')->first();
        $module->fields()->where('name', 'color')->first()->delete();
    }
};
