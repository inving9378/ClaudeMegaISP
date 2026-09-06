<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-13 — Splitter como objeto con ratio, nivel, pérdida y ubicación (aditiva, item roadmap #949).
 *
 * `mapared_tipo_splitter` es el catálogo del que MR-13 depende ("tipo (FK catálogo)"). MR-08
 * (item #944, catálogos completos: cable/splitter/caja/conector) todavía no aterriza, así que
 * esta migración crea SOLO la porción de ese catálogo que MR-13 necesita para no bloquearse
 * (nombre/ratio/número de puertos/pérdida balanceada, semilla D13). El resto del catálogo de
 * MR-08 (tipo_cable, tipo_caja, tipo_conector, splitters desbalanceados D14) sigue siendo
 * responsabilidad de #944; si esa tabla ya existe cuando #944 se ejecute, solo debe ampliarla
 * (columnas nuevas), nunca recrearla.
 *
 * `mapared_splitters` es el splitter como objeto: catálogo, nivel, contenedor físico (NAP, mufa
 * o rack — hoy todos representados por `mapared_devices`) y, para la cascada nivel 1 → nivel 2,
 * el puerto de salida del splitter padre que alimenta la entrada de este. Los puertos propios
 * del splitter (1 entrada + N salidas según el ratio) se generan solos vía
 * `MapaRedPuerto::generarParaSplitter()` (ya construido en MR-10 / item #946 con este caso de uso
 * en mente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_tipo_splitter', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('fabricante')->nullable();
            $table->boolean('balanceado')->default(true);
            $table->string('ratio');
            $table->unsignedSmallInteger('numero_puertos');
            $table->decimal('perdida_db', 5, 2);
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('mapared_splitters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipo_splitter_id')->index();
            $table->unsignedTinyInteger('nivel');
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('puerto_entrada_padre_id')->nullable()->index();
            $table->decimal('perdida_db', 5, 2)->nullable();
            $table->string('etiqueta')->nullable();
            $table->timestamps();

            $table->index('nivel');
        });

        // Semilla D13 — pérdidas de splitter balanceado (dB), editable después desde catálogo.
        $ahora = now();
        DB::table('mapared_tipo_splitter')->insert([
            ['nombre' => 'Splitter balanceado 1:2', 'ratio' => '1:2', 'numero_puertos' => 2, 'perdida_db' => 4.00, 'balanceado' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Splitter balanceado 1:4', 'ratio' => '1:4', 'numero_puertos' => 4, 'perdida_db' => 7.00, 'balanceado' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Splitter balanceado 1:8', 'ratio' => '1:8', 'numero_puertos' => 8, 'perdida_db' => 11.00, 'balanceado' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Splitter balanceado 1:16', 'ratio' => '1:16', 'numero_puertos' => 16, 'perdida_db' => 15.00, 'balanceado' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Splitter balanceado 1:32', 'ratio' => '1:32', 'numero_puertos' => 32, 'perdida_db' => 19.00, 'balanceado' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Splitter balanceado 1:64', 'ratio' => '1:64', 'numero_puertos' => 64, 'perdida_db' => 22.00, 'balanceado' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Splitter balanceado 1:128', 'ratio' => '1:128', 'numero_puertos' => 128, 'perdida_db' => 26.00, 'balanceado' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_splitters');
        Schema::dropIfExists('mapared_tipo_splitter');
    }
};
