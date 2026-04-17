<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('seller_types')->insert([
            ['name' => 'Interno'],
            ['name' => 'Externo'],
            ['name' => 'Distribuidor'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('seller_types')->whereIn('name', ['Interno', 'Externo', 'Distribuidor'])->delete();
    }
};
