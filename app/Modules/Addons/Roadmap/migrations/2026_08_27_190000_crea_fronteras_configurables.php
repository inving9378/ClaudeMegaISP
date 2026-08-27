<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FRONTERAS DURAS GOBERNABLES DESDE LA TORRE (pestaña «Configuración»).
 *
 * POR QUÉ EXISTE. La frontera dura es el ÚNICO control por contenido que no depende de una
 * autodeclaración de un modelo, y hasta hoy vivía entera en `config('circuito.thomas.escalamiento')`:
 * cuatro categorías con ~50 términos que nadie podía ver sin abrir un archivo, mucho menos ajustar.
 * El resultado práctico es que la lista envejecía sola y cada falso positivo se pagaba en la bandeja
 * de Irving sin que él pudiera hacer nada al respecto salvo pedir un cambio de código.
 *
 * ⚠️ ESTO CAMBIA EL MODELO DE SEGURIDAD, a petición explícita de Irving (2026-08-27): antes los
 * topes duros se documentaban como «no se levantan desde ninguna configuración». Ahora SÍ se
 * gobiernan desde la pantalla — encenderlos/apagarlos, editar sus términos y elegir su efecto.
 * Lo que NO cambia: la detección sigue siendo determinista (coincidencia de términos anclada a
 * palabra, `DetectorTerminos`), y cada cambio queda registrado en `torre_compuerta_cambios` con
 * quién, cuándo y el valor anterior.
 *
 * VALORES INICIALES = EXACTAMENTE LO QUE EL CIRCUITO HACE HOY. La tabla se siembra desde
 * `config('circuito.thomas.escalamiento')`, todas las categorías activas y con efecto `bandeja`
 * (= `requiere_irving`, el comportamiento actual). Migrar y cambiar el comportamiento a la vez
 * haría imposible saber cuál de los dos causó lo que pase después — misma regla que la Entrega 1.
 *
 * ADITIVA. Ninguna columna se borra ni se reescribe; `config/circuito.php` sigue siendo el
 * respaldo si esta tabla no existiera o estuviera vacía (`FronterasService::mapa()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('circuito_fronteras')) {
            Schema::create('circuito_fronteras', function (Blueprint $table) {
                $table->id();
                $table->string('categoria', 40)->unique();
                $table->boolean('activa')->default(true);

                // bloquear | bandeja | avisar. varchar y no enum: agregar un efecto no debe pedir
                // un ALTER (misma decisión que `torre_config.nivel_automatizacion`).
                //   · bloquear = requiere_irving Y fuera del pool automático al nacer.
                //   · bandeja  = requiere_irving (lo que hace hoy).
                //   · avisar   = NO retiene; sólo se registra y se cuenta.
                $table->string('efecto', 12)->default('bandeja');

                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circuito_frontera_terminos')) {
            Schema::create('circuito_frontera_terminos', function (Blueprint $table) {
                $table->id();
                $table->string('categoria', 40);
                $table->string('termino', 120);

                // El mismo grado de libertad que `DetectorTerminos::apariciones()`: los términos
                // cortos/ambiguos ('rol', 'prod') exigen palabra completa; los largos admiten
                // flexión ('factura' → 'facturación'). Se guarda por término, no por lista.
                $table->boolean('palabra_completa')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['categoria', 'termino']);
                $table->index('categoria');
            });
        }

        // Siembra idempotente desde la config viva. `updateOrInsert` por llave de negocio: re-correr
        // no duplica y NO pisa lo que Irving haya ajustado después (sólo inserta lo que falte).
        $orden = 0;
        foreach ((array) config('circuito.thomas.escalamiento', []) as $categoria => $terminos) {
            $existe = DB::table('circuito_fronteras')->where('categoria', $categoria)->exists();
            if (! $existe) {
                DB::table('circuito_fronteras')->insert([
                    'categoria'  => $categoria,
                    'activa'     => true,
                    'efecto'     => 'bandeja',
                    'orden'      => $orden,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $orden++;

            foreach ((array) $terminos as $t) {
                $t = trim((string) $t);
                if ($t === '') {
                    continue;
                }
                $ya = DB::table('circuito_frontera_terminos')
                    ->where('categoria', $categoria)->where('termino', $t)->exists();
                if ($ya) {
                    continue;
                }
                DB::table('circuito_frontera_terminos')->insert([
                    'categoria'        => $categoria,
                    'termino'          => $t,
                    // Los cortos/ambiguos exigen palabra completa. La lista es corta y explícita a
                    // propósito: adivinar por longitud metería 'prod' en el saco equivocado.
                    'palabra_completa' => in_array($t, ['rol', 'roles', 'prod', 'secret', 'spei'], true),
                    'activo'           => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        // ── Perillas de la válvula y del techo del autopilot, en el singleton de la Torre ────────
        Schema::table('torre_config', function (Blueprint $table) {
            if (! Schema::hasColumn('torre_config', 'autopilot_max_nivel')) {
                // NULL = «lo gobierna config/circuito.php», que es el estado de hoy. Sólo cuando
                // Irving mueve la perilla en pantalla esta columna deja de ser null y manda.
                $table->string('autopilot_max_nivel', 1)->nullable()->after('nivel_automatizacion');
            }
            if (! Schema::hasColumn('torre_config', 'valvula_activa')) {
                $table->boolean('valvula_activa')->default(true)->after('autopilot_max_nivel');
            }
            if (! Schema::hasColumn('torre_config', 'valvula_modo')) {
                // ablandar | apagar.
                //   · ablandar = una 'mencion' baja la frontera a «requiere Irving», nunca a «pasa».
                //   · apagar   = una 'mencion' hace desaparecer la frontera (lo que hacía hasta hoy).
                $table->string('valvula_modo', 12)->default('ablandar')->after('valvula_activa');
            }
            if (! Schema::hasColumn('torre_config', 'valvula_guarda_termino')) {
                $table->boolean('valvula_guarda_termino')->default(true)->after('valvula_modo');
            }
            if (! Schema::hasColumn('torre_config', 'valvula_guarda_razon')) {
                $table->boolean('valvula_guarda_razon')->default(false)->after('valvula_guarda_termino');
            }
        });
    }

    public function down(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            foreach ([
                'valvula_guarda_razon', 'valvula_guarda_termino',
                'valvula_modo', 'valvula_activa', 'autopilot_max_nivel',
            ] as $col) {
                if (Schema::hasColumn('torre_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('circuito_frontera_terminos');
        Schema::dropIfExists('circuito_fronteras');
    }
};
