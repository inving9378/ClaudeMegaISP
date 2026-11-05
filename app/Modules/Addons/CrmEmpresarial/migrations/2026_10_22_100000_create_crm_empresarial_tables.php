<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Empresarial — modelo de datos del expediente (item #555, épica #554).
 *
 * Aditiva e idempotente. NULL en las columnas nuevas = residencial actual:
 * el flujo de alta/conversión residencial no cambia. No toca `category`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_segmentos')) {
            Schema::create('crm_segmentos', function (Blueprint $t) {
                $t->id();
                $t->string('codigo', 10)->unique();
                $t->string('nombre');
                $t->text('descripcion')->nullable();
                $t->boolean('requiere_expediente')->default(false);
                $t->boolean('activo')->default(true);
                $t->unsignedSmallInteger('orden')->default(0);
                $t->timestamps();
            });
        }

        $seed = [
            ['RES', 'Residencial', 'Hogar o persona física sin expediente empresarial.', false, 1],
            ['NEG', 'Negocio', 'Comercio o negocio pequeño.', true, 2],
            ['PYME', 'PyME', 'Pequeña y mediana empresa.', true, 3],
            ['DED', 'Dedicado', 'Enlace dedicado / corporativo.', true, 4],
        ];
        foreach ($seed as [$codigo, $nombre, $desc, $req, $orden]) {
            if (! DB::table('crm_segmentos')->where('codigo', $codigo)->exists()) {
                DB::table('crm_segmentos')->insert([
                    'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $desc,
                    'requiere_expediente' => $req, 'activo' => true, 'orden' => $orden,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $this->columnas('crm_main_information', function (Blueprint $t) {
            $t->unsignedBigInteger('segmento_id')->nullable()->index();
            $t->enum('tipo_persona', ['fisica', 'moral'])->nullable();
            $t->string('nombre_comercial')->nullable();
        }, ['segmento_id', 'tipo_persona', 'nombre_comercial']);

        $this->columnas('client_additional_information', function (Blueprint $t) {
            $t->unsignedBigInteger('segmento_id')->nullable()->index();
        }, ['segmento_id']);

        if (! Schema::hasTable('crm_expedientes')) {
            Schema::create('crm_expedientes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('crm_id')->nullable()->index();
                $t->unsignedBigInteger('client_id')->nullable()->index();
                $t->foreign('crm_id')->references('id')->on('crms')->nullOnDelete();
                $t->foreignId('segmento_id')->nullable()->constrained('crm_segmentos')->nullOnDelete();
                $t->enum('tipo_persona', ['fisica', 'moral'])->nullable();
                $t->string('razon_social')->nullable();
                $t->string('nombre_comercial')->nullable();
                $t->string('rfc', 20)->nullable()->index();
                $t->string('regimen_fiscal')->nullable();
                $t->date('fecha_constitucion')->nullable();
                $t->string('giro')->nullable();
                $t->string('telefono')->nullable();
                $t->string('email')->nullable();
                $t->string('web')->nullable();
                $t->text('observaciones')->nullable();
                $t->boolean('requiere_expediente_dedicado')->default(false);
                $t->enum('estado', ['borrador', 'en_revision', 'completo'])->default('borrador');
                // Datos fiscales de etapa prospecto (pasan a client_fiscal_data al convertir, item 02).
                $t->string('domicilio_fiscal_calle')->nullable();
                $t->string('domicilio_fiscal_num_ext', 30)->nullable();
                $t->string('domicilio_fiscal_num_int', 30)->nullable();
                $t->unsignedBigInteger('domicilio_fiscal_colony_id')->nullable();
                $t->unsignedBigInteger('domicilio_fiscal_municipality_id')->nullable();
                $t->unsignedBigInteger('domicilio_fiscal_state_id')->nullable();
                $t->string('domicilio_fiscal_cp', 10)->nullable();
                $t->string('uso_cfdi', 10)->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_expediente_representantes')) {
            Schema::create('crm_expediente_representantes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('expediente_id')->constrained('crm_expedientes')->cascadeOnDelete();
                $t->string('nombre');
                $t->string('cargo')->nullable();
                $t->enum('tipo_representacion', ['administrador_unico', 'apoderado', 'representante_legal', 'otro'])->nullable();
                $t->string('instrumento_tipo')->nullable();
                $t->string('instrumento_numero')->nullable();
                $t->date('instrumento_fecha')->nullable();
                $t->string('notaria')->nullable();
                $t->string('entidad')->nullable();
                $t->date('vigencia_facultades')->nullable();
                $t->string('correo_legal')->nullable();
                $t->string('telefono')->nullable();
                $t->enum('estado_validacion', ['pendiente', 'validado', 'rechazado'])->default('pendiente');
                $t->unsignedBigInteger('validado_por')->nullable();
                $t->timestamp('validado_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_expediente_contactos')) {
            Schema::create('crm_expediente_contactos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('expediente_id')->constrained('crm_expedientes')->cascadeOnDelete();
                $t->enum('tipo', ['administrativo', 'tecnico', 'facturacion', 'otro'])->default('otro');
                $t->string('nombre');
                $t->string('puesto')->nullable();
                $t->string('telefono')->nullable();
                $t->string('extension', 20)->nullable();
                $t->string('celular')->nullable();
                $t->string('correo')->nullable();
                $t->string('horario')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_expediente_sitios')) {
            Schema::create('crm_expediente_sitios', function (Blueprint $t) {
                $t->id();
                $t->foreignId('expediente_id')->constrained('crm_expedientes')->cascadeOnDelete();
                $t->string('nombre');
                $t->string('empresa_parque')->nullable();
                $t->string('nave_local')->nullable();
                $t->string('calle')->nullable();
                $t->string('num_ext', 30)->nullable();
                $t->string('num_int', 30)->nullable();
                $t->unsignedBigInteger('colony_id')->nullable();
                $t->unsignedBigInteger('municipality_id')->nullable();
                $t->unsignedBigInteger('state_id')->nullable();
                $t->string('cp', 10)->nullable();
                $t->text('referencias')->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->foreignId('contacto_id')->nullable()->constrained('crm_expediente_contactos')->nullOnDelete();
                $t->string('horario_acceso')->nullable();
                $t->text('restricciones_seguridad')->nullable();
                $t->text('requisitos_ingreso')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        // NULL = domicilio del cliente, como hoy. El domicilio fiscal nunca sustituye al de instalación.
        foreach (['client_internet_services', 'client_custom_services'] as $tabla) {
            $this->columnas($tabla, function (Blueprint $t) {
                $t->unsignedBigInteger('sitio_id')->nullable()->index();
            }, ['sitio_id']);
        }

        if (! Schema::hasTable('crm_expediente_documentos')) {
            Schema::create('crm_expediente_documentos', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('expediente_id')->constrained('crm_expedientes')->cascadeOnDelete();
                $t->string('tipo', 60);
                $t->string('titulo');
                $t->enum('estado_validacion', ['pendiente', 'validado', 'rechazado'])->default('pendiente');
                $t->date('vigencia_fin')->nullable()->index();
                $t->unsignedInteger('version_actual')->default(1);
                $t->unsignedBigInteger('subido_por')->nullable();
                $t->unsignedBigInteger('sustituye_a')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_expediente_documento_versiones')) {
            Schema::create('crm_expediente_documento_versiones', function (Blueprint $t) {
                $t->id();
                $t->foreignId('documento_id')->constrained('crm_expediente_documentos')->cascadeOnDelete();
                $t->unsignedInteger('version');
                $t->uuid('archivo_uuid');
                $t->string('archivo_nombre_original');
                $t->string('mime', 120);
                $t->unsignedBigInteger('bytes')->default(0);
                $t->char('hash', 64)->comment('SHA-256');
                $t->unsignedBigInteger('subido_por')->nullable();
                $t->text('nota_cambio')->nullable();
                $t->timestamp('created_at')->nullable(); // append-only
                $t->unique(['documento_id', 'version']);
            });
        }
    }

    /** Agrega las columnas del closure sólo si ninguna de $nombres existe aún. */
    private function columnas(string $tabla, \Closure $def, array $nombres): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }
        $faltan = array_filter($nombres, fn ($c) => ! Schema::hasColumn($tabla, $c));
        if (count($faltan) === count($nombres)) {
            Schema::table($tabla, $def);
        }
    }

    public function down(): void
    {
        // Forward-only: un expediente no se borra por revertir una migración.
    }
};
