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
        DB::table('ranges_of_sales_sectors')->insert([
            ['sector' => 'A', 'range' => 'Cobre', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'A', 'range' => 'Bronce', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'A', 'range' => 'Plata', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'A', 'range' => 'Oro', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'A', 'range' => 'Diamante', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'B', 'range' => 'Cobre', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'B', 'range' => 'Bronce', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'B', 'range' => 'Plata', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'B', 'range' => 'Oro', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'B', 'range' => 'Diamante', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'C', 'range' => 'Cobre', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'C', 'range' => 'Bronce', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'C', 'range' => 'Plata', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'C', 'range' => 'Oro', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            ['sector' => 'C', 'range' => 'Diamante', 'number_of_prospects' => 0, 'number_of_sales' => 0],
            
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ranges_of_sales_sectors')->whereIn('sector', ['A', 'B', 'C'])->delete();
    }
};
