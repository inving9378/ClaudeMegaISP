<?php

use App\Models\FieldType;
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
        FieldType::create([
            'name' => 'input-scanner'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        FieldType::where('name','input-scanner')->delete();
    }
};
