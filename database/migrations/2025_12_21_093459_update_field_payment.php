<?php

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
       $module = \App\Models\Module::where('name','ClientPayment')->first();
       $module->fields()->where('name','payment_period')->update([
             'default_value' => null,
       ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('name','ClientPayment')->first();
        $module->fields()->where('name','payment_period')->update([
              'default_value' => json_encode([
                  'request'=> '/get-default-value/now-show'
              ])
        ]);
    }
};
