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
        Schema::table('commissions_rules', function (Blueprint $table) {
            // Verificar si la clave foránea existe antes de intentar eliminarla
            if (Schema::hasColumn('commissions_rules', 'seller_id')) {
                $table->dropForeign(['seller_id']);
                $table->dropColumn('seller_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            // Verificar si la columna 'seller_id' no existe antes de intentar agregarla
            if (!Schema::hasColumn('commissions_rules', 'seller_id')) {
                $table->bigInteger('seller_id')->unsigned();
                $table->foreign('seller_id')->references('id')->on('sellers');
            }
        });
    }
};
