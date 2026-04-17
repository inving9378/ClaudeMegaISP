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
        $module = Module::where('name', 'BillingReminder')->first();
        $module->fields()->where('name', 'reminder_1_email_template')->update([
            'search' => json_encode([
                'model' => 'App\Models\DocumentTemplate',
                'id' => 'id',
                'text' => 'name'
            ])
        ]);
        $module->fields()->where('name', 'reminder_2_email_template')->update([
            'search' => json_encode([
                'model' => 'App\Models\DocumentTemplate',
                'id' => 'id',
                'text' => 'name'
            ])
        ]);
        $module->fields()->where('name', 'reminder_3_email_template')->update([
            'search' => json_encode([
                'model' => 'App\Models\DocumentTemplate',
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
        //
    }
};
