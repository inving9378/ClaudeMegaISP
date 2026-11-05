<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UNI-T D2: quién asignó, completó y validó cada orden (aditivo, nulos). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks' => ['assigned_by', 'completed_by', 'validated_by'],
                  'talento_work_orders' => ['assigned_by', 'completed_by', 'validated_by']] as $tabla => $cols) {
            foreach ($cols as $col) {
                if (Schema::hasTable($tabla) && ! Schema::hasColumn($tabla, $col)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->unsignedBigInteger($col)->nullable());
                }
            }
        }
    }

    public function down(): void
    {
        // Aditiva a propósito: no se borran columnas con historial.
    }
};
