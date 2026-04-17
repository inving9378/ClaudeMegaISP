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
        $module = Module::where('name', 'ClientPayment')->first();
        $module->fields()->where('name', 'payment_period')->update([
            'disabled' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ClientPayment')->first();
        $module->fields()->where('name', 'payment_period')->update([
            'disabled' => false,
        ]);
    }
};
