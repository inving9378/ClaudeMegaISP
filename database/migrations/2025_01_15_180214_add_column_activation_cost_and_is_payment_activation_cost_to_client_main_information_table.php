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
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->string('activation_cost')->nullable()->default(299)->after('activation_date');
            $table->boolean('is_payment_activation_cost')->default(false)->after('activation_cost');
        });
        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->create([
            'name' => 'activation_cost',
            'label' => 'Costo de Activación',
            'type' => 15,
            'position' => 30,
            'default_value' => 299,
            'placeholder' => 'Costo de Activación',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->dropColumn('activation_cost');
            $table->dropColumn('is_payment_activation_cost');
        });
        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->where('name', 'activation_cost')->first()->delete();
    }
};
