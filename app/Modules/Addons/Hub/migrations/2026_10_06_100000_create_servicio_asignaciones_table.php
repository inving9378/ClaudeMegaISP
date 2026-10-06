<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Módulos de servicios" del Integration Hub (mismo patrón que Módulos IA):
 * - api_integration_providers.servicio: qué servicio da cada proveedor de tipo
 *   "servicios" (whatsapp, correo, mapas, imagenes_stock, redes_sociales).
 * - Proveedor nuevo "smtp" (servidor de correo) para poder asignar un remitente
 *   distinto por módulo.
 * - servicio_asignaciones: qué usa cada módulo (una línea de WhatsApp o una
 *   integración del Hub). Sin fila = el módulo sigue como siempre.
 */
return new class extends Migration
{
    private const SERVICIO_POR_PROVEEDOR = [
        'evolution'        => 'whatsapp',
        'whatsapp-oficial' => 'whatsapp',
        'pexels'           => 'imagenes_stock',
        'google_maps'      => 'mapas',
        'meta'             => 'redes_sociales',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('api_integration_providers', 'servicio')) {
            Schema::table('api_integration_providers', function (Blueprint $table) {
                $table->string('servicio', 40)->nullable()->after('driver');
            });
        }

        foreach (self::SERVICIO_POR_PROVEEDOR as $slug => $servicio) {
            DB::table('api_integration_providers')->where('slug', $slug)->whereNull('servicio')->update(['servicio' => $servicio]);
        }

        $smtp = DB::table('api_integration_providers')->where('slug', 'smtp')->first();
        if (!$smtp) {
            DB::table('api_integration_providers')->insert([
                'slug'        => 'smtp',
                'name'        => 'Servidor de correo (SMTP)',
                'description' => 'Cuenta de correo para enviar avisos: Gmail, Outlook, Mailgun, Postmark, el servidor propio…',
                'type'        => 'servicios',
                'servicio'    => 'correo',
                'icon'        => 'bi bi-envelope-at',
                'key_format'  => 'contraseña de la cuenta',
                'has_config'  => true,
                'is_system'   => true,
                'active'      => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } elseif ($smtp->deleted_at) {
            DB::table('api_integration_providers')->where('id', $smtp->id)
                ->update(['deleted_at' => null, 'servicio' => 'correo', 'type' => 'servicios', 'updated_at' => now()]);
        }

        if (!Schema::hasTable('servicio_asignaciones')) {
            Schema::create('servicio_asignaciones', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 100)->unique();
                $table->unsignedBigInteger('whatsapp_instance_id')->nullable();
                $table->unsignedBigInteger('api_integration_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('whatsapp_instance_id')->references('id')->on('whatsapp_instances')->nullOnDelete();
                $table->foreign('api_integration_id')->references('id')->on('api_integrations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_asignaciones');
        DB::table('api_integration_providers')->where('slug', 'smtp')->whereNotExists(function ($q) {
            $q->from('api_integrations')->whereColumn('api_integrations.provider', 'api_integration_providers.slug');
        })->delete();
        if (Schema::hasColumn('api_integration_providers', 'servicio')) {
            Schema::table('api_integration_providers', fn (Blueprint $t) => $t->dropColumn('servicio'));
        }
    }
};
