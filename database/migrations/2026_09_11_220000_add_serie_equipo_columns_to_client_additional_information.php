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
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->string('serie_equipo', 20)->nullable()->after('gpon_ont');
            $table->string('serie_equipo_norm', 16)->nullable()->after('serie_equipo');
            $table->string('serie_equipo_origen', 10)->nullable()->after('serie_equipo_norm');
            $table->index('serie_equipo_norm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->dropIndex(['serie_equipo_norm']);
            $table->dropColumn(['serie_equipo', 'serie_equipo_norm', 'serie_equipo_origen']);
        });
    }
};
