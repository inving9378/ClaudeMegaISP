<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Empresarial — configurador de servicios dedicados (item #559, épica #554).
 *
 * Aditiva e idempotente. Es configuración comercial: NO toca client_*_services
 * ni facturación; el servicio real se crea en la activación (fuera de alcance).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_configuraciones_servicio')) {
            return;
        }
        Schema::create('crm_configuraciones_servicio', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('crm_expedientes')->cascadeOnDelete();
            $t->foreignId('sitio_id')->nullable()->constrained('crm_expediente_sitios')->nullOnDelete();

            // Servicio y catálogo (la lista sale SIEMPRE del catálogo, nunca del cliente HTTP)
            $t->enum('servicio_tipo', ['custom', 'internet'])->nullable();
            $t->unsignedBigInteger('servicio_id')->nullable();
            $t->unsignedInteger('velocidad_bajada')->nullable();
            $t->unsignedInteger('velocidad_subida')->nullable();
            $t->boolean('simetrico')->default(false);
            $t->unsignedBigInteger('duration_contract_id')->nullable();
            $t->unsignedSmallInteger('plazo_meses')->nullable();

            // Precio
            $t->decimal('precio_lista', 12, 2)->nullable();
            $t->decimal('precio_aprobado', 12, 2)->nullable();
            $t->decimal('iva_pct', 5, 2)->nullable();
            $t->decimal('iva_importe', 12, 2)->nullable();
            $t->decimal('total', 12, 2)->nullable();
            $t->decimal('costo_instalacion', 12, 2)->nullable();
            $t->decimal('cargo_activacion', 12, 2)->nullable();
            $t->decimal('descuento', 12, 2)->nullable();
            $t->unsignedBigInteger('aprobado_por')->nullable();
            $t->timestamp('aprobado_at')->nullable();
            $t->text('justificacion_precio')->nullable();
            $t->unsignedInteger('version_oferta')->default(1);

            // Técnico
            $t->string('equipo_entrega')->nullable();
            $t->unsignedSmallInteger('ipv4_solicitadas')->nullable(); // la IP definitiva la asigna Red
            $t->boolean('bloque_adicional')->default(false);
            $t->boolean('ipv6')->default(false);
            $t->string('sla_objetivo')->nullable();
            $t->string('monitoreo')->nullable();
            $t->string('horario_soporte')->nullable();
            $t->string('tiempo_atencion')->nullable();
            $t->enum('factibilidad', ['pendiente', 'viable', 'no_viable'])->default('pendiente');
            $t->unsignedBigInteger('factibilidad_por')->nullable();
            $t->text('observaciones_ingenieria')->nullable();
            $t->text('infraestructura_existente')->nullable();
            $t->date('fecha_estimada_entrega')->nullable();
            $t->date('vigencia_propuesta')->nullable();

            // Redundancia desglosada (doble ruta != redundancia total)
            $t->unsignedTinyInteger('red_rutas_fisicas')->nullable();
            $t->boolean('red_proteccion_logica')->default(false);
            $t->boolean('red_diversidad_equipos')->default(false);
            $t->boolean('red_diversidad_proveedores')->default(false);
            $t->decimal('red_disponibilidad_contractual', 5, 3)->nullable();
            $t->enum('red_conmutacion', ['automatica', 'manual'])->nullable();
            $t->boolean('red_verificada')->default(false); // sólo lo verificado/aprobado se refleja en documentos

            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        // Forward-only.
    }
};
