<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\FieldType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $field = FieldType::where('name', 'select-component-client-prospect')->first();
        if($field) return;

        DB::table('field_types')->insert([
            'name' => 'select-component-client-prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $field = FieldType::where('name', 'select-component-client-prospect')->first();
        if(!$field) return;

        DB::table('field_types')
            ->where('name', 'select-component-client-prospect')
            ->delete();
    }
};
