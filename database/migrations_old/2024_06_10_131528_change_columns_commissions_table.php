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
        Schema::table('commissions', function (Blueprint $table) {
            // eliminar columnas anteriores
            $table->dropColumn(['date', 'percentage', 'amount']);
            // nuevas columnas 
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('period');
            $table->integer('number_sales');
            $table->integer('number_prospects');
            $table->string('zone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table->dropColumn(['start_date', 'end_date', 'period', 'number_sales', 'number_prospects', 'zone']);
        $table->timestamp('date')->useCurrent();
        $table->integer('percentage')->nullable();
        $table->decimal('amount', 8, 2)->nullable();
        
    }
};
