<?php

use App\Modules\Core\Configuracion\Models\FieldType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        FieldType::create([
            'name' => 'select-component-client-task',
        ]);
    }

    public function down(): void
    {
        FieldType::where('name', 'select-component-client-task')->delete();
    }
};
