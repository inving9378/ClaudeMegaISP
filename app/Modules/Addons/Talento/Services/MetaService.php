<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoMeta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * UNI-T M2b: vigencia, versionado y precedencia de metas (una por concepto+alcance).
 * Informativo: no cambia reglas económicas ni pagos. Apagado por config('unificacion.metas_m2').
 */
class MetaService
{
    /** Más específico primero. */
    public const PRECEDENCIA = ['persona', 'equipo', 'puesto', 'rol', 'global'];

    /**
     * Crea una meta nueva; si ya hay una vigente para concepto+alcance, la cierra y abre version+1.
     * Rechaza solapes de vigencia (p. ej. una versión con vigencia futura ya registrada).
     */
    public function crear(array $d, ?int $userId = null): TalentoMeta
    {
        $tipo  = $d['alcance_tipo'] ?? 'global';
        if (! in_array($tipo, self::PRECEDENCIA, true)) {
            throw new InvalidArgumentException("alcance_tipo inválido: {$tipo}");
        }
        $clave = $tipo === 'global' ? null : (isset($d['alcance_clave']) ? (string) $d['alcance_clave'] : null);
        if ($tipo !== 'global' && $clave === null) {
            throw new InvalidArgumentException('alcance_clave requerida para alcance '.$tipo);
        }
        $desde = Carbon::parse($d['vigente_desde'] ?? today())->startOfDay();

        return DB::transaction(function () use ($d, $tipo, $clave, $desde, $userId) {
            $base = TalentoMeta::query()->where('concepto', $d['concepto'])->where('alcance_tipo', $tipo)
                ->where('alcance_clave', $clave)->lockForUpdate();

            // Solape: alguna versión que empiece en o después de la nueva no puede coexistir.
            if ((clone $base)->whereDate('vigente_desde', '>=', $desde)->exists()) {
                throw new InvalidArgumentException('Ya existe una meta vigente o posterior para ese concepto y alcance que se solapa.');
            }

            $previa = (clone $base)->whereNull('vigente_hasta')->orderByDesc('version')->first();
            if ($previa) {
                $previa->update(['vigente_hasta' => $desde->copy()->subDay()->toDateString()]);
            }
            $ultima = (clone $base)->max('version');

            $meta = TalentoMeta::create([
                'concepto'      => $d['concepto'],
                'alcance_tipo'  => $tipo,
                'alcance_clave' => $clave,
                'periodo'       => $d['periodo'] ?? 'semanal',
                'valor'         => $d['valor'],
                'unidad'        => $d['unidad'] ?? null,
                'afecta_pago'   => (bool) ($d['afecta_pago'] ?? false),
                'version'       => ((int) $ultima) + 1,
                'reemplaza_id'  => $previa?->id,
                'vigente_desde' => $desde->toDateString(),
                'origen'        => $d['origen'] ?? 'manual',
                'origen_id'     => $d['origen_id'] ?? null,
                'motivo'        => $d['motivo'] ?? null,
            ]);

            $log = activity('talento')->performedOn($meta)
                ->withProperties(['concepto' => $meta->concepto, 'version' => $meta->version, 'valor' => $meta->valor, 'reemplaza_id' => $meta->reemplaza_id]);
            if ($userId && ($u = \App\Models\User::find($userId))) {
                $log->causedBy($u);
            }
            $log->log('Meta creada (versión '.$meta->version.')');

            return $meta;
        });
    }

    /**
     * Meta vigente en $fecha para el concepto, la más específica según el contexto.
     * $ctx: ['persona'=>id, 'equipo'=>clave, 'puesto'=>clave, 'rol'=>clave] (todos opcionales).
     */
    public function resolver(string $concepto, array $ctx = [], $fecha = null): ?TalentoMeta
    {
        $f = Carbon::parse($fecha ?? today())->toDateString();

        foreach (self::PRECEDENCIA as $tipo) {
            $q = TalentoMeta::query()->where('concepto', $concepto)->where('alcance_tipo', $tipo)
                ->whereDate('vigente_desde', '<=', $f)
                ->where(fn ($w) => $w->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $f));
            if ($tipo === 'global') {
                $q->whereNull('alcance_clave');
            } elseif (isset($ctx[$tipo])) {
                $q->where('alcance_clave', (string) $ctx[$tipo]);
            } else {
                continue;
            }
            if ($m = $q->orderByDesc('version')->first()) {
                return $m;
            }
        }

        return null;
    }
}
