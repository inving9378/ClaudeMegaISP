<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja asignado en "Módulos de servicios" lo que cada módulo usa HOY
 * (decisión de Irving 2026-10-06: la actualización no debe apagar nada):
 * - Avisos de WhatsApp que hoy salen por la línea principal → esa línea.
 * - Videos de stock → la integración principal de Pexels.
 * - Facebook/Instagram → la integración principal de Meta.
 * Correo y mapas no se asignan: sin fila siguen usando el servidor de correo y
 * la llave de mapas de siempre. Idempotente: no pisa asignaciones existentes.
 */
return new class extends Migration
{
    private const WHATSAPP = [
        'pagos.pago_recibido', 'domiciliacion.liga', 'domiciliacion.avisos_cobro', 'facturacion.pausas',
        'facturacion.recordatorio', 'talento.recordatorio_documentos', 'talento.ot_asignada',
        'embajadores.compartir', 'referidos.avisos', 'portalpago.resumen_diario', 'warroom.minutas',
        'warroom.acciones', 'flotas.geocercas', 'flotas.documentos', 'flotas.suscripciones',
    ];

    private const INTEGRACION = [
        'marketing.broll' => 'pexels',
        'marketing.meta'  => 'meta',
    ];

    public function up(): void
    {
        $linea = DB::table('whatsapp_instances')->where('active', true)->where('default_instance', true)->value('id');
        if ($linea) {
            foreach (self::WHATSAPP as $clave) {
                $this->asignar($clave, ['whatsapp_instance_id' => $linea]);
            }
        }

        foreach (self::INTEGRACION as $clave => $proveedor) {
            $id = DB::table('api_integrations')
                ->where('company_id', 1)->where('provider', $proveedor)
                ->where('is_default_for_provider', true)->where('active', true)
                ->whereNull('deleted_at')->value('id');
            if ($id) {
                $this->asignar($clave, ['api_integration_id' => $id]);
            }
        }
    }

    private function asignar(string $clave, array $destino): void
    {
        if (DB::table('servicio_asignaciones')->where('clave', $clave)->exists()) {
            return;
        }
        DB::table('servicio_asignaciones')->insert(['clave' => $clave, 'created_at' => now(), 'updated_at' => now()] + $destino);
    }

    public function down(): void
    {
        // Sin reversa: quitar las asignaciones devuelve cada módulo a "como siempre", que es lo mismo.
    }
};
