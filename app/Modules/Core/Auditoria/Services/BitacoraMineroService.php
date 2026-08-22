<?php

namespace App\Modules\Core\Auditoria\Services;

use App\Models\ActivityLog;
use App\Models\AuditoriaMineroCursor;
use App\Models\AuditoriaSenal;
use Illuminate\Support\Carbon;

/**
 * Minero de bitácora (item #1016, sub-item de #1004 §4).
 *
 * Consume `activity_log` (única fuente v1, decisión q3 de Irving) de forma
 * incremental por cursor (decisión q4: scheduled cada 15 min, ventana
 * incremental) y detecta la señal "intención abandonada" (decisión q2):
 * un registro se crea y nadie vuelve a tocarlo dentro de la ventana
 * configurable, MIENTRAS el mismo usuario sigue generando otra actividad en
 * el sistema — eso separa "se distrajo y no volvió" de "terminó su sesión y
 * se fue" (que no es abandono, es cierre normal y NO debe señalarse).
 *
 * Solo detección: escribe en `auditoria_senales` (tabla read-only para
 * revisión humana, decisión q1 — MVP lector-only). Nunca ejecuta acciones.
 */
class BitacoraMineroService
{
    private const FUENTE = 'activity_log';
    private const TIPO_INTENCION_ABANDONADA = 'intencion_abandonada';
    private const TIPO_EVENTO_INICIO = 'created';

    public function minar(): array
    {
        if (!config('auditoria.minero_bitacora.enabled', true)) {
            return ['habilitado' => false, 'procesadas' => 0, 'senales' => 0];
        }

        $ventanaMin = (int) config('auditoria.minero_bitacora.ventana_abandono_minutos', 30);
        $loteMaximo = (int) config('auditoria.minero_bitacora.lote_maximo', 2000);

        $cursor = AuditoriaMineroCursor::firstOrCreate(
            ['fuente' => self::FUENTE],
            ['ultimo_id' => 0, 'updated_at' => now()]
        );

        // Solo evaluamos filas cuya ventana de N minutos YA CERRÓ — si todavía
        // puede llegar un evento de cierre, aún no es candidata.
        $corteVentanaCerrada = now()->subMinutes($ventanaMin);

        $filas = ActivityLog::where('id', '>', $cursor->ultimo_id)
            ->where('created_at', '<=', $corteVentanaCerrada)
            ->orderBy('id')
            ->limit($loteMaximo)
            ->get(['id', 'event', 'subject_type', 'subject_id', 'causer_id', 'created_at']);

        if ($filas->isEmpty()) {
            return ['habilitado' => true, 'procesadas' => 0, 'senales' => 0];
        }

        $insertadas = 0;
        foreach ($filas as $fila) {
            if ($fila->event === self::TIPO_EVENTO_INICIO
                && $fila->causer_id !== null
                && !empty($fila->subject_type)
                && $this->esIntencionAbandonada($fila, $ventanaMin)
            ) {
                if ($this->registrarSenal($fila, $ventanaMin)) {
                    $insertadas++;
                }
            }
        }

        $cursor->ultimo_id = (int) $filas->max('id');
        $cursor->updated_at = now();
        $cursor->save();

        return ['habilitado' => true, 'procesadas' => $filas->count(), 'senales' => $insertadas];
    }

    private function esIntencionAbandonada(ActivityLog $fila, int $ventanaMin): bool
    {
        /** @var Carbon $limite */
        $limite = $fila->created_at->copy()->addMinutes($ventanaMin);

        $seRetomo = ActivityLog::where('subject_type', $fila->subject_type)
            ->where('subject_id', $fila->subject_id)
            ->where('id', '!=', $fila->id)
            ->whereIn('event', ['updated', 'deleted'])
            ->where('created_at', '>', $fila->created_at)
            ->where('created_at', '<=', $limite)
            ->exists();

        if ($seRetomo) {
            return false;
        }

        // El usuario debe haber seguido activo (otro registro) dentro de la
        // ventana; si no hizo nada más, no se distingue de un cierre de
        // sesión normal — se descarta, a propósito conservador.
        return ActivityLog::where('causer_id', $fila->causer_id)
            ->where('id', '!=', $fila->id)
            ->where(function ($q) use ($fila) {
                $q->where('subject_type', '!=', $fila->subject_type)
                    ->orWhere('subject_id', '!=', $fila->subject_id);
            })
            ->where('created_at', '>', $fila->created_at)
            ->where('created_at', '<=', $limite)
            ->exists();
    }

    private function registrarSenal(ActivityLog $fila, int $ventanaMin): bool
    {
        $dedupeKey = sha1(self::TIPO_INTENCION_ABANDONADA . '|' . self::FUENTE . '|' . $fila->id);

        if (AuditoriaSenal::where('dedupe_key', $dedupeKey)->exists()) {
            return false;
        }

        AuditoriaSenal::create([
            'tipo'        => self::TIPO_INTENCION_ABANDONADA,
            'fuente'      => self::FUENTE,
            'ocurrido_en' => $fila->created_at,
            'payload'     => [
                'activity_log_id'   => $fila->id,
                'subject_type'      => $fila->subject_type,
                'subject_id'        => $fila->subject_id,
                'causer_id'         => $fila->causer_id,
                'ventana_minutos'   => $ventanaMin,
            ],
            'dedupe_key'  => $dedupeKey,
            'revisado'    => false,
            'created_at'  => now(),
        ]);

        return true;
    }
}
