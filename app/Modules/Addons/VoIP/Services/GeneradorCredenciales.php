<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\ProvisionEstado;
use Illuminate\Support\Str;

/**
 * Genera las credenciales de AMI y ARI y las deja en el `.env` (#9990718 §3, paso 7).
 *
 * **No las inventa en silencio ni deja el hueco para que alguien las ponga a
 * mano.** Se generan, se escriben en el `.env` y en la configuración de Asterisk
 * **en la misma operación**, y queda registrado en la tabla de estado *quién y
 * cuándo* las generó.
 *
 * El valor NUNCA se registra: ni en la tabla, ni en el log, ni en el reporte. Lo
 * que se guarda es el **origen** —que fue generado automáticamente, por qué versión
 * y en qué momento—, que es lo que hace falta para auditar sin exponer el secreto.
 * Una credencial en un log es una credencial comprometida.
 *
 * Si ya existe una credencial en el `.env`, **se respeta**: regenerarla en cada
 * provisión rompería la central de un cliente que reintenta.
 */
class GeneradorCredenciales
{
    public function generarYPersistir(string $uuid, string $version): array
    {
        $env = new EscritorEnv(base_path('.env'));

        $claves  = [];
        $origen  = [];

        if (! $this->yaDefinida('AMI_SECRET')) {
            $claves['AMI_SECRET'] = Str::random(40);
            $origen['AMI_SECRET'] = 'generada';
        } else {
            $origen['AMI_SECRET'] = 'preexistente';
        }

        if (! $this->yaDefinida('ASTERISK_ARI_PASS')) {
            $claves['ASTERISK_ARI_PASS'] = Str::random(40);
            $origen['ASTERISK_ARI_PASS'] = 'generada';
        } else {
            $origen['ASTERISK_ARI_PASS'] = 'preexistente';
        }

        $resultado = ['respaldo' => null, 'escritas' => [], 'avisos' => []];

        if ($claves !== []) {
            $resultado = $env->escribir($claves);
            // La config en memoria tiene los valores viejos: sin esto, las
            // plantillas se rellenarían con lo anterior.
            foreach ($claves as $k => $v) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
            }
            config([
                'voip.ami_pass' => $claves['AMI_SECRET']        ?? config('voip.ami_pass'),
                'voip.ari_pass' => $claves['ASTERISK_ARI_PASS'] ?? config('voip.ari_pass'),
            ]);
        }

        // Auditoría: el ORIGEN, nunca el valor.
        ProvisionEstado::where('ejecucion_uuid', $uuid)->where('paso', 'credenciales')
            ->update(['detalle' => json_encode([
                'origen'          => $origen,
                'generadas_por'   => 'provisionador v' . $version,
                'generadas_en'    => now()->toIso8601String(),
                'respaldo_env'    => $resultado['respaldo'] ? basename($resultado['respaldo']) : null,
                'avisos'          => $resultado['avisos'],
                'nota'            => 'El VALOR no se registra: una credencial en un log es una credencial comprometida.',
            ])]);

        return [
            'origen'       => $origen,
            'respaldo_env' => $resultado['respaldo'] ? basename($resultado['respaldo']) : null,
            'avisos'       => $resultado['avisos'],
        ];
    }

    private function yaDefinida(string $clave): bool
    {
        $v = env($clave);

        // Un marcador sin sustituir no cuenta como definida: es justo el caso que
        // dejó ASTERISK_AMI_PASS=CAMBIAR_AMI_PASS_VOIP durante meses.
        return ! empty($v) && ! Str::startsWith(strtoupper((string) $v), ['CAMBIAR', 'CHANGEME', 'TODO', 'XXX']);
    }
}
