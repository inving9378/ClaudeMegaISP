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
        Schema::create('ranges_of_sales_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('sector');
            $table->string('range');
            $table->integer('number_of_prospects');
            $table->integer('number_of_sales');
            $table->unique(['sector', 'range']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ranges_of_sales_sectors');
    }
};
