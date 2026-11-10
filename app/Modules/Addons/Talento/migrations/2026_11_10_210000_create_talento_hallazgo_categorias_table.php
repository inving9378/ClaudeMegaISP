<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1311 (Recorridos, fase a) — catálogo editable de categorías de hallazgo de planta externa.
 * Las categorías iniciales son las que definió Irving en #1309; «Otro» va al final. La descripción
 * del hallazgo es obligatoria antes de enviar el recorrido en todas las categorías. Nunca se
 * borran: se desactivan con activo=0 (los hallazgos guardan su categoria_id).
 */
return new class extends Migration
{
    private const INICIALES = [
        'Poste dañado o inclinado',
        'Fibra colgada o baja',
        'Fibra expuesta o dañada',
        'NAP o cierre abierto o sin etiquetar',
        'Vegetación sobre la fibra',
        'Obra civil requerida',
        'Riesgo por terceros',
        'Robo o vandalismo',
        'Otro',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('talento_hallazgo_categorias')) {
            Schema::create('talento_hallazgo_categorias', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120)->unique('thcat_nombre_unique');
                $table->string('descripcion', 255)->nullable();
                // baja|media|alta|critica — solo sugerencia al clasificar; el hallazgo guarda la suya.
                $table->string('severidad_sugerida', 10)->nullable();
                $table->boolean('activo')->default(true);
                $table->unsignedInteger('orden')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        foreach (self::INICIALES as $i => $nombre) {
            if (! DB::table('talento_hallazgo_categorias')->where('nombre', $nombre)->exists()) {
                DB::table('talento_hallazgo_categorias')->insert([
                    'nombre' => $nombre, 'activo' => true, 'orden' => ($i + 1) * 10,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_hallazgo_categorias');
    }
};
