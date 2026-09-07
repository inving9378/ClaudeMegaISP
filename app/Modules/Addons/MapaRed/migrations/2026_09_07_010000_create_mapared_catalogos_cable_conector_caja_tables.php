<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MR-08 Fase 1 (item roadmap #9990503, sub-item de #944) — catálogos D13/D14/D15 del Mapa de Red.
 *
 * `mapared_tipo_splitter` YA EXISTE (MR-13/#949) con la porción balanceada (D13); aquí solo se
 * amplía (Schema::table, defensivo con hasColumn) para agregar el desbalanceado (D14) sin tocar
 * `perdida_db` (que sigue siendo la de balanceados, consumida por
 * `MapaRedSplitter::getPerdidaEfectivaDbAttribute()`).
 *
 * `mapared_tipo_cable`/`mapared_tipo_conector`/`mapared_tipo_caja` son tablas nuevas. Los
 * consumidores que ya las mencionan como pendientes (`MapaRedCable::tipo_cable_id` referencia
 * BLANDA sin FK real, `FiberColorScheme`, `OpticalBudgetService::ATENUACION_DB_KM`) siguen sin
 * wiring — eso es de una fase posterior; esta migración solo aterriza el catálogo (datos), no
 * conecta a quien los usaría.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mapared_tipo_cable')) {
            Schema::create('mapared_tipo_cable', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('fabricante')->nullable();
                $table->unsignedSmallInteger('numero_hilos');
                $table->unsignedSmallInteger('hilos_por_buffer')->default(12);
                $table->enum('esquema_color', ['eia_tia_598', 'abnt_nbr_14771'])->default('eia_tia_598');
                $table->json('atenuacion_db_km')->nullable();
                $table->decimal('precio', 10, 2)->nullable();
                $table->string('unidad_medida')->default('metro');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mapared_tipo_conector')) {
            Schema::create('mapared_tipo_conector', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->decimal('perdida_db', 5, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mapared_tipo_caja')) {
            Schema::create('mapared_tipo_caja', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->unsignedSmallInteger('capacidad_puertos')->nullable();
                $table->unsignedSmallInteger('capacidad_fusiones')->nullable();
                $table->string('ip_rating')->nullable();
                $table->string('ik_rating')->nullable();
                $table->timestamps();
            });
        }

        // Ampliación defensiva de mapared_tipo_splitter (MR-13/#949) para D14 (desbalanceados).
        Schema::table('mapared_tipo_splitter', function (Blueprint $table) {
            if (! Schema::hasColumn('mapared_tipo_splitter', 'tipo_conector_id')) {
                $table->unsignedBigInteger('tipo_conector_id')->nullable()->index()->after('precio');
            }
            if (! Schema::hasColumn('mapared_tipo_splitter', 'perdida_paso_db')) {
                $table->decimal('perdida_paso_db', 5, 2)->nullable()->after('tipo_conector_id');
            }
            if (! Schema::hasColumn('mapared_tipo_splitter', 'perdida_derivacion_db')) {
                $table->decimal('perdida_derivacion_db', 5, 2)->nullable()->after('perdida_paso_db');
            }
        });

        // `perdida_db` nació NOT NULL (MR-13, solo balanceados). D14 (desbalanceados) la deja en
        // null (usa perdida_paso_db/perdida_derivacion_db en su lugar) -> la columna debe admitirlo.
        Schema::table('mapared_tipo_splitter', function (Blueprint $table) {
            $table->decimal('perdida_db', 5, 2)->nullable()->change();
        });

        $ahora = now();

        // D15 — atenuación de fibra por ventana óptica (dB/km), mismos valores que
        // OpticalBudgetService::ATENUACION_DB_KM.
        if (DB::table('mapared_tipo_cable')->count() === 0) {
            DB::table('mapared_tipo_cable')->insert([
                'nombre' => 'Fibra monomodo G.652D',
                'fabricante' => null,
                'numero_hilos' => 12,
                'hilos_por_buffer' => 12,
                'esquema_color' => 'eia_tia_598',
                'atenuacion_db_km' => json_encode(['1310' => 0.35, '1490' => 0.25, '1550' => 0.25]),
                'precio' => null,
                'unidad_medida' => 'metro',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        // D15 — pérdidas por tipo de empalme/conector, mismos valores que
        // MapaRedEmpalme::PERDIDA_DB_DEFAULT.
        if (DB::table('mapared_tipo_conector')->count() === 0) {
            DB::table('mapared_tipo_conector')->insert([
                ['nombre' => 'Fusión', 'perdida_db' => 0.10, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Mecánico', 'perdida_db' => 0.30, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Conectorizado', 'perdida_db' => 0.50, 'created_at' => $ahora, 'updated_at' => $ahora],
            ]);
        }

        // Catálogo típico de cajas NAP (sin D-decisión específica, valores razonables de mercado).
        if (DB::table('mapared_tipo_caja')->count() === 0) {
            DB::table('mapared_tipo_caja')->insert([
                ['nombre' => 'NAP 8 puertos', 'capacidad_puertos' => 8, 'capacidad_fusiones' => 12, 'ip_rating' => 'IP65', 'ik_rating' => 'IK08', 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'NAP 16 puertos', 'capacidad_puertos' => 16, 'capacidad_fusiones' => 24, 'ip_rating' => 'IP65', 'ik_rating' => 'IK08', 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Mufa de empalme 24 fusiones', 'capacidad_puertos' => null, 'capacidad_fusiones' => 24, 'ip_rating' => 'IP68', 'ik_rating' => 'IK08', 'created_at' => $ahora, 'updated_at' => $ahora],
            ]);
        }

        // D14 — splitter desbalanceado (paso/derivación dB), complemento de D13 (balanceado, ya sembrado).
        if (DB::table('mapared_tipo_splitter')->where('balanceado', false)->count() === 0) {
            DB::table('mapared_tipo_splitter')->insert([
                ['nombre' => 'Splitter desbalanceado 50/50', 'ratio' => '50/50', 'numero_puertos' => 2, 'balanceado' => false, 'perdida_db' => null, 'perdida_paso_db' => 3.6, 'perdida_derivacion_db' => 3.6, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Splitter desbalanceado 40/60', 'ratio' => '40/60', 'numero_puertos' => 2, 'balanceado' => false, 'perdida_db' => null, 'perdida_paso_db' => 2.7, 'perdida_derivacion_db' => 4.4, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Splitter desbalanceado 30/70', 'ratio' => '30/70', 'numero_puertos' => 2, 'balanceado' => false, 'perdida_db' => null, 'perdida_paso_db' => 1.9, 'perdida_derivacion_db' => 5.6, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Splitter desbalanceado 20/80', 'ratio' => '20/80', 'numero_puertos' => 2, 'balanceado' => false, 'perdida_db' => null, 'perdida_paso_db' => 1.3, 'perdida_derivacion_db' => 7.4, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Splitter desbalanceado 10/90', 'ratio' => '10/90', 'numero_puertos' => 2, 'balanceado' => false, 'perdida_db' => null, 'perdida_paso_db' => 0.8, 'perdida_derivacion_db' => 11.0, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Splitter desbalanceado 5/95', 'ratio' => '5/95', 'numero_puertos' => 2, 'balanceado' => false, 'perdida_db' => null, 'perdida_paso_db' => 0.4, 'perdida_derivacion_db' => 14.0, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Splitter desbalanceado 1/99', 'ratio' => '1/99', 'numero_puertos' => 2, 'balanceado' => false, 'perdida_db' => null, 'perdida_paso_db' => 0.3, 'perdida_derivacion_db' => 21.0, 'created_at' => $ahora, 'updated_at' => $ahora],
            ]);
        }
    }

    public function down(): void
    {
        // Quita las filas D14 (desbalanceadas) sembradas por esta migración antes de tirar las
        // columnas que las describen, para no dejar splitters huérfanos sin perdida_db/paso/derivación.
        DB::table('mapared_tipo_splitter')->where('balanceado', false)->delete();

        Schema::table('mapared_tipo_splitter', function (Blueprint $table) {
            if (Schema::hasColumn('mapared_tipo_splitter', 'perdida_derivacion_db')) {
                $table->dropColumn('perdida_derivacion_db');
            }
            if (Schema::hasColumn('mapared_tipo_splitter', 'perdida_paso_db')) {
                $table->dropColumn('perdida_paso_db');
            }
            if (Schema::hasColumn('mapared_tipo_splitter', 'tipo_conector_id')) {
                $table->dropColumn('tipo_conector_id');
            }
        });

        Schema::table('mapared_tipo_splitter', function (Blueprint $table) {
            $table->decimal('perdida_db', 5, 2)->nullable(false)->change();
        });

        Schema::dropIfExists('mapared_tipo_caja');
        Schema::dropIfExists('mapared_tipo_conector');
        Schema::dropIfExists('mapared_tipo_cable');
    }
};
