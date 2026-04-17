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
        \App\Models\FieldModule::where('module_id', 13)->where('name', 'user')->update(['label' => 'Usuario WEB']);
        \App\Models\FieldModule::where('module_id', 13)->where('name', 'password')->update(['label' => 'Contraseña WEB']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
