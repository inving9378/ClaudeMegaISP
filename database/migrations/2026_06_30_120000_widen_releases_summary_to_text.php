<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `summary` era VARCHAR(255) → las notas de release generadas por IA lo desbordan
     * (SQLSTATE[22001] 1406 al guardar la release en el paso final del deploy). Se amplía
     * a TEXT para que quepa el cuerpo completo del release de GitHub.
     */
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->text('summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->string('summary')->nullable()->change();
        });
    }
};
