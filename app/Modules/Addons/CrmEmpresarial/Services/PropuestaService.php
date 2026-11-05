<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use App\Modules\Addons\CrmEmpresarial\Models\CrmConfiguracionServicio;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Models\CrmPropuesta;
use App\Modules\Addons\CrmEmpresarial\Models\CrmPropuestaHistorial;
use App\Modules\Addons\CrmEmpresarial\Models\CrmPropuestaVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Propuestas comerciales (item #562): versiones que nunca se sobrescriben y máquina de estados.
 * «aceptada» NO equivale a contrato firmado (la firma llega en otro ítem de la épica).
 */
class PropuestaService
{
    public const ESTADOS = ['borrador', 'en_revision', 'aprobada', 'enviada', 'vista',
        'cambios_solicitados', 'aceptada', 'rechazada', 'vencida', 'sustituida'];

    /** Transiciones válidas por estado de origen (sustituida sólo se alcanza por nuevaVersion). */
    public const TRANSICIONES = [
        'borrador' => ['en_revision'],
        'en_revision' => ['aprobada', 'borrador'],
        'aprobada' => ['enviada', 'vencida'],
        'enviada' => ['vista', 'cambios_solicitados', 'aceptada', 'rechazada', 'vencida'],
        'vista' => ['cambios_solicitados', 'aceptada', 'rechazada', 'vencida'],
        'cambios_solicitados' => ['rechazada'],
    ];

    /** Claves del snapshot que, al cambiar, exigen una versión nueva. */
    public const CAMPOS_EDITABLES = ['precio', 'plazo_meses', 'capacidad', 'condiciones', 'notas'];

    public function __construct(private ExpedienteValidador $validador = new ExpedienteValidador())
    {
    }

    public function crear(CrmExpediente $e, ?int $userId = null, ?int $vigenciaDias = null, ?int $emisionId = null): CrmPropuesta
    {
        $v = $this->validador->validar($e, 'emitir_propuesta');
        if (! $v['permitido']) {
            throw ValidationException::withMessages([
                'expediente' => ['Faltan datos para emitir propuesta: ' . implode(', ', array_merge($v['faltantes'], $v['errores']))],
            ]);
        }

        return DB::transaction(function () use ($e, $userId, $vigenciaDias, $emisionId) {
            $p = CrmPropuesta::create([
                'expediente_id' => $e->id,
                'folio' => 'PROP-' . $e->id . '-' . strtoupper(substr(uniqid(), -6)),
                'estado' => 'borrador', 'version_actual' => 1, 'creada_por' => $userId,
            ]);
            $this->insertarVersion($p, 1, $this->snapshotDe($e), $userId, $vigenciaDias, $emisionId, null);

            return $p->fresh();
        });
    }

    /** Edita en sitio SOLO un borrador; cualquier otro estado exige nuevaVersion(). */
    public function editarBorrador(CrmPropuestaVersion $v, array $cambios, ?int $userId = null): CrmPropuestaVersion
    {
        if ($v->estado !== 'borrador') {
            throw ValidationException::withMessages(['estado' => ["La versión {$v->version} está «{$v->estado}»: ya no se edita; crea una versión nueva."]]);
        }
        $v->update(['snapshot' => $this->aplicar($v->snapshot, $cambios)]);

        return $v->fresh();
    }

    /** La versión vigente pasa a «sustituida» y nace la siguiente como borrador. */
    public function nuevaVersion(CrmPropuesta $p, array $cambios, string $motivo, ?int $userId = null, ?int $vigenciaDias = null): CrmPropuestaVersion
    {
        return DB::transaction(function () use ($p, $cambios, $motivo, $userId, $vigenciaDias) {
            $p = CrmPropuesta::lockForUpdate()->findOrFail($p->id);
            $actual = $p->vigente();
            if (in_array($actual->estado, ['aceptada', 'rechazada', 'vencida', 'sustituida'], true)) {
                throw ValidationException::withMessages(['estado' => ["La propuesta está «{$actual->estado}»: no admite versión nueva."]]);
            }
            $this->registrar($actual, $actual->estado, 'sustituida', $motivo, $userId);
            $actual->update(['estado' => 'sustituida']);

            $n = $p->version_actual + 1;
            $nueva = $this->insertarVersion($p, $n, $this->aplicar($actual->snapshot, $cambios), $userId, $vigenciaDias, null, $motivo);
            $p->update(['version_actual' => $n, 'estado' => 'borrador']);

            return $nueva;
        });
    }

    public function transicionar(CrmPropuestaVersion $v, string $a, ?int $userId = null, ?string $motivo = null): CrmPropuestaVersion
    {
        return DB::transaction(function () use ($v, $a, $userId, $motivo) {
            $v = CrmPropuestaVersion::lockForUpdate()->findOrFail($v->id);
            if (! in_array($a, self::TRANSICIONES[$v->estado] ?? [], true)) {
                throw ValidationException::withMessages(['estado' => ["Transición no permitida: {$v->estado} → {$a}."]]);
            }
            if (in_array($a, ['rechazada', 'cambios_solicitados', 'borrador'], true) && blank($motivo)) {
                throw ValidationException::withMessages(['motivo' => ['Indica el motivo.']]);
            }
            $datos = ['estado' => $a];
            if ($a === 'aprobada') {
                $datos += ['aprobada_por' => $userId, 'aprobada_at' => now()];
            }
            $this->registrar($v, $v->estado, $a, $motivo, $userId);
            $v->update($datos);
            // Sólo la versión vigente espeja su estado en la cabecera.
            CrmPropuesta::where('id', $v->propuesta_id)->where('version_actual', $v->version)->update(['estado' => $a]);

            return $v->fresh();
        });
    }

    /** Vence las versiones aprobadas/enviadas/vistas cuya vigencia ya pasó. Devuelve cuántas. */
    public function vencerVencidas(): int
    {
        $n = 0;
        CrmPropuestaVersion::whereIn('estado', ['aprobada', 'enviada', 'vista'])
            ->whereNotNull('vigencia_hasta')->whereDate('vigencia_hasta', '<', now()->toDateString())
            ->each(function (CrmPropuestaVersion $v) use (&$n) {
                $this->transicionar($v, 'vencida', null, 'Vigencia vencida');
                $n++;
            });

        return $n;
    }

    private function insertarVersion(CrmPropuesta $p, int $n, array $snapshot, ?int $userId, ?int $dias, ?int $emisionId, ?string $motivo): CrmPropuestaVersion
    {
        $v = CrmPropuestaVersion::create([
            'propuesta_id' => $p->id, 'version' => $n, 'estado' => 'borrador', 'snapshot' => $snapshot,
            'vigencia_hasta' => now()->addDays($dias ?? 30)->toDateString(),
            'emision_id' => $emisionId, 'motivo' => $motivo, 'creada_por' => $userId,
        ]);
        $this->registrar($v, null, 'borrador', $motivo, $userId);

        return $v;
    }

    private function registrar(CrmPropuestaVersion $v, ?string $de, string $a, ?string $motivo, ?int $userId): void
    {
        CrmPropuestaHistorial::create(['propuesta_version_id' => $v->id, 'de' => $de, 'a' => $a, 'motivo' => $motivo, 'por' => $userId]);
    }

    private function aplicar(array $snapshot, array $cambios): array
    {
        return array_merge($snapshot, array_intersect_key($cambios, array_flip(self::CAMPOS_EDITABLES)));
    }

    /** Foto de lo que se propone: expediente + servicios configurados (precios/plazo/capacidad). */
    private function snapshotDe(CrmExpediente $e): array
    {
        return [
            'expediente' => ['id' => $e->id, 'razon_social' => $e->razon_social ?? null, 'rfc' => $e->rfc ?? null],
            'servicios' => CrmConfiguracionServicio::where('expediente_id', $e->id)->orderBy('id')->get()->toArray(),
            'condiciones' => null, 'notas' => null,
        ];
    }
}
