<?php

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;

/**
 * FASE 1 (unificación WhatsApp) — Refleja la instancia REAL 'meganet-ventas' en el panel.
 *
 * El número vivo (ventas + bot + conciliación) vive hoy en el mundo Marketing
 * (Integration Hub + App\Models\Marketing\Setting). El panel del addon WhatsAppAgent
 * (tabla whatsapp_instances) estaba vacío. Esta migración lo REFLEJA: da de alta una
 * fila leyendo los datos REALES de Marketing — NO inventa credenciales y NO crea nada
 * en Evolution (no pasa por store()/createInstance, que duplicaría la instancia).
 *
 * Banderas de seguridad de Fase 1 (reflejar, NO cablear):
 *   - default_instance = FALSE  → el notificador SPEI (PaymentApplicationService::…
 *     sendAndLog(null) → active()->default()) NO empieza a enviar por el número real.
 *     Marcarlo default es decisión de Fase 4 (unificar el sender).
 *   - active = TRUE → se muestra en el panel y su estado se sincroniza en vivo.
 *   - status = 'disconnected' de arranque → NO persistimos un estado falso; el panel
 *     consulta Evolution en vivo (connectionState/meganet-ventas) y lo refresca.
 *
 * Idempotente: firstOrCreate por slug. down() borra solo la fila de reflejo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Datos REALES desde la config de Marketing, con fallback a config/whatsapp
        // (que es lo que el EvolutionApiService del addon usa para autenticar — misma key).
        $instanceName = \App\Models\Marketing\Setting::get('evolution_instance_name', 1)
            ?: (string) config('whatsapp.default_instance', 'meganet-ventas');

        $apiUrl = \App\Models\Marketing\Setting::get('evolution_api_url', 1)
            ?: (string) config('whatsapp.api_url');

        // La api_key vive en el Hub/env (Settings de Marketing la trae vacía); es la MISMA
        // key de 64 chars que config('whatsapp.api_key'), la que de hecho autentica contra
        // Evolution. Se guarda CIFRADA (el modelo la descifra con getDecryptedApiKeyAttribute).
        $apiKey = \App\Models\Marketing\Setting::get('evolution_api_key', 1)
            ?: (string) config('whatsapp.api_key');

        WhatsAppInstance::firstOrCreate(
            ['slug' => 'meganet-ventas'],
            [
                'name'             => 'Ventas (producción)',
                'instance_id'      => $instanceName,
                'api_url'          => $apiUrl,
                'api_key'          => $apiKey !== '' ? Crypt::encryptString($apiKey) : '',
                'default_instance' => false, // Fase 1: reflejar sin cablear (default = Fase 4)
                'active'           => true,
                'phone_number'     => null,  // display opcional; el número real lo pone Irving luego
                'status'           => 'disconnected', // se refleja en vivo; no persistimos estado falso
            ]
        );
        // webhook_secret se autogenera en el hook creating() del modelo si va vacío.
    }

    public function down(): void
    {
        WhatsAppInstance::where('slug', 'meganet-ventas')->delete();
    }
};
