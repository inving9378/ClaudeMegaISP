<?php

namespace App\Modules\Addons\Payments\Support;

use App\Modules\Addons\Payments\Models\ConciliationSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Estado EDITABLE de los interruptores de automatización de conciliación.
 *
 * La lógica de conciliación pregunta aquí (NO directo a config()) si un flag
 * está activo. Fuente de verdad: tabla conciliation_settings; si no hay fila
 * para la clave, cae a config('payments.<key>') (que a su vez lee el .env).
 * Así, cambiar un toggle tiene efecto REAL inmediato, con el .env como default.
 *
 * Se gestionan 4 claves: el MAESTRO wa_conciliation (activa el enrutamiento de
 * comprobantes) + los 3 de comportamiento (wa_autorespond, auto_apply_enabled,
 * id_cliente_auto_apply). Todas leen la tabla con config/.env como fallback.
 */
class ConciliationSettings
{
    /** Claves gestionadas → metadatos para la pantalla de interruptores. */
    public const KEYS = [
        'wa_conciliation' => [
            'label'   => 'Sistema de conciliación por WhatsApp',
            'help'    => 'Interruptor general. Si está apagado, el sistema ignora los comprobantes que llegan por WhatsApp (se comportan como mensajes normales del bot de ventas). Debe estar encendido para que funcione todo lo demás de abajo.',
            'danger'  => false,
            'master'  => true,
        ],
        'wa_autorespond' => [
            'label'   => 'La IA responde por WhatsApp a los clientes',
            'help'    => 'Si está encendido, el asistente contesta automáticamente por WhatsApp durante la identificación del pago. Apagado: la IA razona y prepara todo pero NO envía ningún mensaje al cliente.',
            'danger'  => false,
        ],
        'auto_apply_enabled' => [
            'label'   => 'La IA aplica automáticamente los pagos identificados con referencia MEG',
            'help'    => 'Si está encendido, cuando el cliente se identifica de forma inequívoca (referencia MEG), el pago se aplica solo, sin intervención humana. Apagado: todo pasa por la cola de revisión manual.',
            'danger'  => true,
        ],
        'id_cliente_auto_apply' => [
            'label'   => 'El ID de cliente también aplica automático (sin confirmación humana)',
            'help'    => 'Si está encendido, identificar por número de cliente cuenta como identificación inequívoca y también puede aplicar solo. Recomendado dejarlo APAGADO al inicio: el número de cliente es más fácil de teclear mal que la referencia MEG.',
            'danger'  => true,
        ],
    ];

    /** ¿Está activo el flag? Tabla manda; sin fila → config('payments.<key>'). */
    public static function enabled(string $key): bool
    {
        // Defensa: si la tabla aún no existe (migración no corrida) usa el fallback.
        if (Schema::hasTable('conciliation_settings')) {
            $row = ConciliationSetting::where('key', $key)->first();
            if ($row) {
                return (bool) $row->enabled;
            }
        }
        return (bool) config('payments.' . $key, false);
    }

    /** Guarda el estado del flag (upsert) y registra quién lo cambió. */
    public static function set(string $key, bool $value, ?int $userId = null): void
    {
        if (!array_key_exists($key, self::KEYS)) {
            return; // sólo se gestionan las claves conocidas
        }
        ConciliationSetting::updateOrCreate(
            ['key' => $key],
            ['enabled' => $value, 'updated_by' => $userId]
        );
    }

    /** Estado efectivo de las 3 claves + su fuente (db|env) para la UI. */
    public static function state(): array
    {
        $rows = Schema::hasTable('conciliation_settings')
            ? ConciliationSetting::whereIn('key', array_keys(self::KEYS))->get()->keyBy('key')
            : collect();

        $out = [];
        foreach (self::KEYS as $key => $meta) {
            $row = $rows->get($key);
            $out[] = array_merge($meta, [
                'key'     => $key,
                'enabled' => $row ? (bool) $row->enabled : (bool) config('payments.' . $key, false),
                'source'  => $row ? 'db' : 'env',
            ]);
        }
        return $out;
    }
}
