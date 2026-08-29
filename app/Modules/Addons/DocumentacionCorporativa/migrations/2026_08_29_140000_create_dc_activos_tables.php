<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 3, apartados V (activos e infraestructura),
 * VIII (activos digitales y tecnológicos) y XI (inventario de accesos).
 *
 * `dc_activos`             → torres, antenas, postería, fibra, redes troncales,
 *                            equipo de transmisión, vehículos, cómputo,
 *                            herramientas, centros de distribución y bodegas.
 *                            `lat`/`lng` nullable: solo los activos con
 *                            ubicación física real (torre/posteria/fibra/...)
 *                            las llevan; cómputo o herramienta normalmente no.
 * `dc_activos_digitales`   → sistemas, plataformas, software propio, servidores,
 *                            bases de datos, dominios, licencias, etc.
 *                            `titular` es OBLIGATORIO a nivel de esquema: la
 *                            solicitud exige que todo activo digital declare a
 *                            nombre de quién está, para poder detectar cuando NO
 *                            es MEGANET. `titularidad_estado` es el resultado
 *                            de esa detección (ver DcActivoDigital::boot()).
 * `dc_inventario_accesos`  → institución/sistema, quién es el custodio y dónde
 *                            se resguarda el secreto — NUNCA el secreto mismo.
 *                            No existe columna de contraseña/token/CLABE
 *                            completa ni llave privada, ni cifrada ni nullable:
 *                            así no hay nada que capturar, migrar, exportar por
 *                            error ni filtrar en un respaldo de MySQL. El campo
 *                            de credencial se rinde como constante en código
 *                            (`DcInventarioAcceso::getCredencialAttribute()`),
 *                            nunca como columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_activos')) {
            Schema::create('dc_activos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->enum('categoria', [
                    'torre', 'antena', 'posteria', 'fibra', 'red_troncal',
                    'equipo_transmision', 'vehiculo', 'computo', 'herramienta',
                    'centro_distribucion', 'bodega', 'otro',
                ]);
                $table->string('nombre');
                $table->text('descripcion')->nullable();
                $table->string('identificador')->nullable()->comment('serie, placa o código de inventario');
                $table->string('ubicacion')->nullable()->comment('referencia textual del sitio');
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->date('fecha_adquisicion')->nullable();
                $table->decimal('valor_adquisicion', 12, 2)->nullable();
                $table->enum('estado', ['activo', 'baja', 'mantenimiento'])->default('activo');
                $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'categoria']);
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('dc_activos_digitales')) {
            Schema::create('dc_activos_digitales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->enum('tipo', [
                    'sistema', 'plataforma', 'software_propio', 'servidor', 'base_datos',
                    'app_movil', 'sitio_web', 'panel', 'licencia', 'respaldo', 'dominio',
                    'correo_corporativo', 'red_social', 'plataforma_marketing',
                ]);
                $table->string('nombre');
                $table->text('descripcion')->nullable();
                $table->string('proveedor')->nullable();
                // Obligatorio: sin titular declarado no hay nada que regularizar ni que auditar.
                $table->string('titular');
                $table->enum('titularidad_estado', ['regular', 'titularidad_a_regularizar'])
                    ->default('regular');
                $table->string('url')->nullable();
                $table->date('fecha_alta')->nullable();
                $table->date('vigencia_fin')->nullable()->comment('vencimiento de dominio/licencia, si aplica');
                $table->decimal('costo_periodico', 12, 2)->nullable();
                $table->string('periodicidad_costo')->nullable();
                $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'tipo']);
                $table->index('titularidad_estado');
            });
        }

        if (! Schema::hasTable('dc_inventario_accesos')) {
            Schema::create('dc_inventario_accesos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->enum('tipo', [
                    'cuenta_bancaria', 'linea_credito', 'cuenta_inversion', 'terminal_pv',
                    'usuario_sistema', 'firma_autorizada', 'token',
                ]);
                $table->string('institucion_o_sistema');
                // Máximo 4 caracteres A PROPÓSITO: nunca la cuenta/CLABE/tarjeta completa
                // (apartado XI, conceptos 104/117/118/119), solo lo suficiente para
                // identificar el registro en una lista.
                $table->string('identificador_publico', 4)->nullable();
                $table->string('titular')->nullable();
                $table->boolean('secreto_existe')->default(true);
                $table->foreignId('custodio_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ubicacion_resguardo')->nullable();
                $table->date('fecha_ultima_revision')->nullable();
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_inventario_accesos');
        Schema::dropIfExists('dc_activos_digitales');
        Schema::dropIfExists('dc_activos');
    }
};
