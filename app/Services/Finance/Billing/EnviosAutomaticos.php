<?php

namespace App\Services\Finance\Billing;

use App\Console\Commands\Active\CancelarColasCobroCommand;
use App\Models\BillingConfig;
use App\Models\BillingNotification;
use App\Models\User;
use App\Services\Finance\Invoice\EnvioPrefactura;
use App\Support\ArchivoObligatorio;
use App\Support\Entorno;
use App\Support\Seguridad\SuperAdministrador;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Configuración › Finanzas › «Envíos automáticos» (2026-10-22). Un solo interruptor maneja juntas la
 * pausa general (billing_config.envio_pausado) y la etapa de reactivación (reactivacion_nivel):
 *  - encendido = sin pausa y ritmo «prueba» (20 por corrida, 100 al día) o «normal»;
 *  - apagado = pausa puesta. No existe «encendido pero cerrado».
 * El ritmo se cambia aparte mientras está encendido.
 *
 * Al encender hay que decidir qué pasa con lo acumulado en la cola:
 *  - enviar todo: sale en el orden de siempre, con los límites de la etapa;
 *  - descartar lo de más de 48 h (recomendado): se cancelan, no se borran, y queda un archivo para
 *    revertir con `billing:cancelar-colas --revertir=… --ejecutar`;
 *  - enviar en lotes por hora: tope por hora hasta que se vacíe lo acumulado; luego se quita solo.
 *
 * «Depurar cola» cancela lo que lleve más de N horas en cola (por tipo o todo), esté pausado o no,
 * con el mismo archivo reversible. Solo super-administrador. Cada cambio queda en activity_log
 * (log_name facturacion-envios).
 */
class EnviosAutomaticos
{
    public const ROLES = SuperAdministrador::ROLES;

    public const ENVIAR_TODO = 'enviar_todo';
    public const DESCARTAR_VIEJOS = 'descartar_48h';
    public const LOTES_POR_HORA = 'lotes_por_hora';
    public const OPCIONES = [self::DESCARTAR_VIEJOS, self::ENVIAR_TODO, self::LOTES_POR_HORA];

    public const HORAS_DESCARTE = 48;
    public const LOTE_MIN = 10;
    public const LOTE_MAX = 500;
    public const LOTE_SUGERIDO = 50;
    public const DEPURAR_MAX_HORAS = 8760;

    public const TIPOS = [
        BillingNotification::TYPE_PREFACTURA => 'Prefacturas',
        BillingNotification::TYPE_RECIBO     => 'Recibos',
        BillingNotification::TYPE_TICKET     => 'Tickets',
    ];

    public const LOG = 'facturacion-envios';

    public static function puedeGestionar(?User $user): bool
    {
        return SuperAdministrador::es($user);
    }

    public function estado(): array
    {
        $config = BillingConfig::current();
        $porId = $config->envio_pausado_cambiado_por ? User::find($config->envio_pausado_cambiado_por) : null;

        return [
            'encendido' => $this->encendido($config),
            'entorno_pruebas' => $this->entornoPruebas(),
            'pausado' => (bool) $config->envio_pausado,
            'cambiado_por' => $porId ? trim($porId->name . ' ' . $porId->father_last_name) : null,
            'cambiado_at' => $config->envio_pausado_cambiado_at?->toIso8601String(),
            'ritmo' => $config->envio_lote_por_hora ? [
                'por_hora' => (int) $config->envio_lote_por_hora,
                'acumulado_hasta' => $config->envio_lote_hasta?->toIso8601String(),
                'enviados_ultima_hora' => $this->enviadosUltimaHora(),
            ] : null,
            'cola' => $this->cola(),
            'etapa' => $this->etapa(),
            'proforma_anticipada' => $this->proformaAnticipada(),
            'opciones' => [
                'horas_descarte' => self::HORAS_DESCARTE,
                'lote_min' => self::LOTE_MIN,
                'lote_max' => self::LOTE_MAX,
                'lote_sugerido' => self::LOTE_SUGERIDO,
                'depurar_max_horas' => self::DEPURAR_MAX_HORAS,
                'prueba_por_corrida' => ReactivacionEnvios::PRUEBA_POR_CORRIDA,
                'prueba_por_dia' => ReactivacionEnvios::PRUEBA_POR_DIA,
            ],
        ];
    }

    /** En cola = en espera. Por tipo: cuántas, cuántas pasan de 48 h y la más vieja. */
    public function cola(): array
    {
        $limite = now()->subHours(self::HORAS_DESCARTE);
        $filas = DB::table('billing_notifications')->where('status', BillingNotification::STATUS_WAITING)
            ->selectRaw('document_type, count(*) as total, sum(created_at < ?) as viejas, sum(send_by_email = 1) as con_correo, min(created_at) as mas_vieja', [$limite])
            ->groupBy('document_type')
            ->get()
            ->keyBy('document_type');

        $tipos = [];
        foreach (self::TIPOS as $tipo => $nombre) {
            $f = $filas[$tipo] ?? null;
            $tipos[] = [
                'tipo' => $tipo,
                'nombre' => $nombre,
                'total' => (int) ($f->total ?? 0),
                'viejas' => (int) ($f->viejas ?? 0),
                'con_correo' => (int) ($f->con_correo ?? 0),
                'mas_vieja' => $f?->mas_vieja ? Carbon::parse($f->mas_vieja)->toIso8601String() : null,
            ];
        }
        $otros = $filas->except(array_keys(self::TIPOS))->sum('total');

        return [
            'tipos' => $tipos,
            'total' => array_sum(array_column($tipos, 'total')) + (int) $otros,
            'viejas' => array_sum(array_column($tipos, 'viejas')),
            'con_correo' => array_sum(array_column($tipos, 'con_correo')),
            'otros' => (int) $otros,
        ];
    }

    /** Encendido = sin pausa y con ritmo (prueba o normal). Cualquier otra combinación es apagado. */
    public function encendido(?BillingConfig $config = null): bool
    {
        $config ??= BillingConfig::current();

        return ! $config->envio_pausado && in_array($config->reactivacion_nivel, ReactivacionEnvios::RITMOS, true);
    }

    /** Apagar: confirmación simple, efecto inmediato. El ritmo queda guardado para la próxima vez. */
    public function apagar(User $user): array
    {
        $this->exigirPermiso($user);

        return DB::transaction(function () use ($user) {
            $config = $this->bloquear();
            if (! $this->encendido($config) && $config->envio_pausado) {
                throw new InvalidArgumentException('Los envíos ya están apagados.');
            }
            $cola = $this->cola();
            $this->guardar($config, true, $user, ['envio_lote_por_hora' => null, 'envio_lote_hasta' => null]);
            $this->auditar($user, $config, 'Envíos automáticos apagados', ['antes' => 'encendidos', 'despues' => 'apagados',
                'ritmo' => $config->reactivacion_nivel, 'cola' => $cola['total']]);

            return ['message' => 'Envíos automáticos apagados. Lo que se genere queda en cola.'];
        });
    }

    /**
     * Encender: qué hacer con lo acumulado ($opcion) y el ritmo (prueba o normal), en una sola
     * transacción. Si al descartar no se puede guardar el archivo para revertir, no cambia nada.
     *
     * @param int|null $porHora solo con LOTES_POR_HORA
     */
    public function encender(User $user, string $opcion, string $ritmo, ?int $porHora = null): array
    {
        $this->exigirPermiso($user);
        if (! in_array($opcion, self::OPCIONES, true)) {
            throw new InvalidArgumentException('Elige qué hacer con lo acumulado.');
        }
        if ($opcion === self::LOTES_POR_HORA && ($porHora === null || $porHora < self::LOTE_MIN || $porHora > self::LOTE_MAX)) {
            throw new InvalidArgumentException('El lote por hora va de ' . self::LOTE_MIN . ' a ' . self::LOTE_MAX . '.');
        }
        $this->validarRitmo($ritmo);

        return DB::transaction(function () use ($user, $opcion, $ritmo, $porHora) {
            $config = $this->bloquear();
            if ($this->encendido($config)) {
                throw new InvalidArgumentException('Los envíos ya están encendidos.');
            }
            $ahora = now();
            $antes = $this->cola();
            $detalle = ['antes' => 'apagados', 'despues' => 'encendidos', 'opcion' => $opcion, 'ritmo' => $ritmo, 'cola_antes' => $antes['total']];
            $extra = ['envio_lote_por_hora' => null, 'envio_lote_hasta' => null];
            $mensaje = 'Envíos encendidos: lo acumulado sale con el ritmo elegido.';

            if ($opcion === self::DESCARTAR_VIEJOS) {
                [$n, $archivo, $porTipo] = $this->cancelarViejos($ahora->copy()->subHours(self::HORAS_DESCARTE), null,
                    'Descartada al encender los envíos: llevaba más de ' . self::HORAS_DESCARTE . ' h en cola.', 'reactivacion');
                $detalle += ['descartadas' => $n, 'descartadas_por_tipo' => $porTipo, 'archivo_para_revertir' => $archivo];
                $mensaje = "Envíos encendidos. Se descartaron {$n} avisos con más de " . self::HORAS_DESCARTE . ' h en cola (no se borran; se pueden revertir).';
            } elseif ($opcion === self::LOTES_POR_HORA) {
                $extra = ['envio_lote_por_hora' => $porHora, 'envio_lote_hasta' => $ahora];
                $detalle += ['por_hora' => $porHora];
                $mensaje = "Envíos encendidos en lotes de {$porHora} por hora hasta vaciar lo acumulado.";
            }

            $this->guardar($config, false, $user, $extra + $this->camposRitmo($ritmo, $user));
            $this->auditar($user, $config, 'Envíos automáticos encendidos', $detalle);

            return ['message' => $mensaje . ' Ritmo: ' . $this->nombreRitmo($ritmo) . '.'] + $detalle;
        });
    }

    /** Cambiar el ritmo con los envíos encendidos (prueba ↔ normal). */
    public function cambiarRitmo(User $user, string $ritmo): array
    {
        $this->exigirPermiso($user);
        $this->validarRitmo($ritmo);

        return DB::transaction(function () use ($user, $ritmo) {
            $config = $this->bloquear();
            if (! $this->encendido($config)) {
                throw new InvalidArgumentException('El ritmo se cambia con los envíos encendidos; al encender se elige.');
            }
            $antes = $config->reactivacion_nivel;
            if ($antes === $ritmo) {
                throw new InvalidArgumentException('El ritmo ya es ' . $this->nombreRitmo($ritmo) . '.');
            }
            $config->forceFill($this->camposRitmo($ritmo, $user))->save();
            $this->auditar($user, $config, 'Ritmo de envío cambiado', ['antes' => $antes, 'despues' => $ritmo]);

            return ['message' => 'Ritmo de envío: ' . $this->nombreRitmo($ritmo) . '.'];
        });
    }

    private function camposRitmo(string $ritmo, User $user): array
    {
        $prueba = $ritmo === ReactivacionEnvios::NIVEL_PRUEBA;

        return [
            'reactivacion_nivel' => $ritmo,
            'reactivacion_limite_corrida' => $prueba ? ReactivacionEnvios::PRUEBA_POR_CORRIDA : null,
            'reactivacion_limite_diario' => $prueba ? ReactivacionEnvios::PRUEBA_POR_DIA : null,
            'reactivacion_cambiada_por' => $user->id,
            'reactivacion_cambiada_at' => now(),
        ];
    }

    private function nombreRitmo(string $ritmo): string
    {
        return $ritmo === ReactivacionEnvios::NIVEL_PRUEBA
            ? 'Prueba (' . ReactivacionEnvios::PRUEBA_POR_CORRIDA . ' por corrida, ' . ReactivacionEnvios::PRUEBA_POR_DIA . ' al día)'
            : 'Normal';
    }

    private function validarRitmo(string $ritmo): void
    {
        if (! in_array($ritmo, ReactivacionEnvios::RITMOS, true)) {
            throw new InvalidArgumentException('Elige el ritmo: Prueba o Normal.');
        }
    }

    /** Cuántos avisos llevan más de $horas en cola, por tipo (lo que «Depurar cola» cancelaría). */
    public function conteoDepuracion(int $horas): array
    {
        $this->validarHoras($horas);
        $filas = $this->enColaDesdeAntes(now()->subHours($horas), null)->toBase()
            ->selectRaw('document_type, count(*) as n')->groupBy('document_type')->pluck('n', 'document_type');
        $tipos = [];
        foreach (self::TIPOS as $tipo => $nombre) {
            $tipos[] = ['tipo' => $tipo, 'nombre' => $nombre, 'n' => (int) ($filas[$tipo] ?? 0)];
        }

        return ['horas' => $horas, 'tipos' => $tipos, 'total' => (int) $filas->sum()];
    }

    /**
     * «Depurar cola»: cancela lo que lleve más de $horas en cola, de un tipo o de todos. $esperado es
     * el conteo que vio la persona: si la cola cambió no se toca nada y se avisa con el nuevo.
     * Funciona aunque los envíos estén pausados.
     */
    public function depurar(User $user, int $horas, ?string $tipo, int $esperado): array
    {
        $this->exigirPermiso($user);
        $this->validarHoras($horas);
        if ($tipo !== null && ! array_key_exists($tipo, self::TIPOS)) {
            throw new InvalidArgumentException('Tipo de aviso desconocido.');
        }

        return DB::transaction(function () use ($user, $horas, $tipo, $esperado) {
            $limite = now()->subHours($horas);
            $actual = $this->enColaDesdeAntes($limite, $tipo)->lockForUpdate()->count();
            if ($actual !== $esperado) {
                throw new InvalidArgumentException("La cola cambió: ahora son {$actual}, no {$esperado}. Revisa el conteo y confirma de nuevo.");
            }
            if ($actual === 0) {
                throw new InvalidArgumentException('No hay avisos con más de ' . $horas . ' h en cola' . ($tipo ? ' de ese tipo' : '') . '.');
            }
            [$n, $archivo, $porTipo] = $this->cancelarViejos($limite, $tipo, "Descartada al depurar la cola: llevaba más de {$horas} h en cola.", 'depuracion');
            $detalle = ['horas' => $horas, 'tipo' => $tipo ?? 'todos', 'canceladas' => $n, 'por_tipo' => $porTipo, 'archivo_para_revertir' => $archivo,
                'pausado' => (bool) BillingConfig::current()->envio_pausado];
            $this->auditar($user, BillingConfig::current(), 'Cola de avisos depurada', $detalle);

            return ['message' => "Se cancelaron {$n} avisos con más de {$horas} h en cola (no se borran; se pueden revertir)."] + $detalle;
        });
    }

    private function enColaDesdeAntes(Carbon $limite, ?string $tipo): Builder
    {
        return BillingNotification::where('status', BillingNotification::STATUS_WAITING)
            ->where('created_at', '<', $limite)
            ->when($tipo, fn ($q) => $q->where('document_type', $tipo));
    }

    private function validarHoras(int $horas): void
    {
        if ($horas < 1 || $horas > self::DEPURAR_MAX_HORAS) {
            throw new InvalidArgumentException('Las horas van de 1 a ' . self::DEPURAR_MAX_HORAS . '.');
        }
    }

    /**
     * Para billing:send-pending-notifications: cuántos más puede mandar esta corrida por el ritmo
     * de lotes (null = sin ritmo). Cuando ya no queda nada acumulado, quita el ritmo.
     */
    public function cupoPorRitmo(?BillingConfig $config = null): ?int
    {
        $config ??= BillingConfig::current();
        if (! $config->envio_lote_por_hora) {
            return null;
        }
        // Solo cuenta lo que el envío puede llegar a mandar (con correo): un ticket sin correo se
        // queda en espera para siempre y no debe dejar el ritmo puesto.
        $quedan = BillingNotification::where('status', BillingNotification::STATUS_WAITING)
            ->where('send_by_email', true)
            ->where('created_at', '<=', $config->envio_lote_hasta ?? now())
            ->exists();
        if (! $quedan) {
            $config->forceFill(['envio_lote_por_hora' => null, 'envio_lote_hasta' => null])->save();
            activity(self::LOG)->performedOn($config)->withProperties(['motivo' => 'acumulado vacío'])->log('Ritmo por hora terminado: ya no queda nada acumulado');

            return null;
        }

        return max(0, (int) $config->envio_lote_por_hora - $this->enviadosUltimaHora());
    }

    private function enviadosUltimaHora(): int
    {
        return BillingNotification::where('status', BillingNotification::STATUS_SENT)
            ->where('enviado_at', '>=', now()->subHour())
            ->count();
    }

    /**
     * Cancela (no borra) lo que está en cola desde antes de $limite, de un tipo o de todos.
     *
     * @return array{0: int, 1: ?string, 2: array<string, int>} [canceladas, archivo, por tipo]
     */
    private function cancelarViejos(Carbon $limite, ?string $tipo, string $nota, string $prefijoArchivo): array
    {
        [, , , $cancelado] = CancelarColasCobroCommand::COLAS['billing_notifications'];

        return $this->cambiarEstado($this->enColaDesdeAntes($limite, $tipo), $cancelado, $nota, $prefijoArchivo);
    }

    /**
     * Pone $estadoNuevo a las filas de $consulta y deja el archivo que lo revierte con
     * `billing:cancelar-colas --revertir=… --ejecutar` (mismo formato: «cancelado» es el estado que se
     * puso y cada fila guarda el que tenía). Sirve para cancelar y, desde «Ver detalle de la cola»,
     * para reactivar. Debe correr dentro de una transacción.
     *
     * @return array{0: int, 1: ?string, 2: array<string, int>} [cambiadas, archivo, por tipo]
     */
    public function cambiarEstado(Builder $consulta, string $estadoNuevo, string $nota, string $prefijoArchivo): array
    {
        [, , , , $colNota] = CancelarColasCobroCommand::COLAS['billing_notifications'];
        $resultado = ['origen' => 'Configuración › Envíos automáticos', 'tabla' => 'billing_notifications', 'cancelado' => $estadoNuevo,
            'columna_nota' => $colNota, 'ejecutado_at' => now()->toIso8601String(), 'cancelados' => []];
        $porTipo = [];

        $consulta->select(['id', 'document_type', 'status', 'updated_at', $colNota])
            ->lockForUpdate()
            ->chunkById(1000, function ($lote) use (&$resultado, &$porTipo, $estadoNuevo, $colNota, $nota) {
                $ahora = now()->startOfSecond();
                DB::table('billing_notifications')->whereIn('id', $lote->pluck('id'))
                    ->update(['status' => $estadoNuevo, $colNota => $nota, 'updated_at' => $ahora]);
                foreach ($lote as $fila) {
                    // Mismo formato que billing:cancelar-colas, para revertir con su --revertir.
                    $resultado['cancelados'][] = [$fila->id, (string) $fila->status, (string) $fila->getRawOriginal('updated_at'), $ahora->format('Y-m-d H:i:s'), $fila->{$colNota}];
                    $porTipo[$fila->document_type] = ($porTipo[$fila->document_type] ?? 0) + 1;
                }
            });

        $n = count($resultado['cancelados']);
        if ($n === 0) {
            return [0, null, []];
        }
        // El sufijo evita que dos cambios en el mismo segundo se pisen el archivo (cada uno es lo único que lo revierte).
        $archivo = CancelarColasCobroCommand::DIRECTORIO . "/{$prefijoArchivo}-" . now()->format('Ymd-His') . '-' . Str::lower(Str::random(4)) . '.json';
        // Sin archivo no hay cómo revertir: si no se guarda, la excepción deshace el cambio (corre en transacción).
        ArchivoObligatorio::guardar($archivo, json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return [$n, 'storage/app/' . $archivo, $porTipo];
    }

    private function bloquear(): BillingConfig
    {
        $id = BillingConfig::current()->id;

        return BillingConfig::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function guardar(BillingConfig $config, bool $pausado, User $user, array $extra): void
    {
        $config->forceFill([
            'envio_pausado' => $pausado,
            'envio_pausado_cambiado_por' => $user->id,
            'envio_pausado_cambiado_at' => now(),
        ] + $extra)->save();
    }

    public function auditar(User $user, BillingConfig $config, string $descripcion, array $detalle): void
    {
        activity(self::LOG)->causedBy($user)->performedOn($config)->withProperties($detalle)->log($descripcion);
    }

    public function exigirPermiso(User $user): void
    {
        if (! self::puedeGestionar($user)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Solo un super-administrador puede encender o apagar los envíos automáticos.');
        }
    }

    /** La etapa vigente y el ritmo guardado (que se usa al encender), con quién y cuándo lo cambió. */
    public function etapa(): array
    {
        $etapa = new ReactivacionEnvios();
        $config = BillingConfig::current();
        $por = $config->reactivacion_cambiada_por ? User::find($config->reactivacion_cambiada_por) : null;
        $ritmo = in_array($config->reactivacion_nivel, ReactivacionEnvios::RITMOS, true) ? $config->reactivacion_nivel : null;

        return ['nombre' => $etapa->etapa()] + $etapa->resumenConfig() + [
            'ritmo' => $ritmo,
            'ritmo_nombre' => $ritmo ? $this->nombreRitmo($ritmo) : null,
            'cambiada_por' => $por ? trim($por->name . ' ' . $por->father_last_name) : null,
            'cambiada_at' => $config->reactivacion_cambiada_at?->toIso8601String(),
        ];
    }

    /**
     * Fuera de producción, a dónde va el correo (el buzón de pruebas, enmascarado; null = se cancela)
     * y que el WhatsApp de facturación está bloqueado. En producción, null.
     */
    private function entornoPruebas(): ?array
    {
        if (Entorno::esProduccion()) {
            return null;
        }
        $destino = trim((string) config('mail.interceptar.destino'));
        $buzon = filter_var($destino, FILTER_VALIDATE_EMAIL) ? preg_replace('/^(.).*(@.*)$/', '$1•••$2', $destino) : null;

        return ['buzon' => $buzon, 'prefijo' => (string) config('mail.interceptar.prefijo', '[DEV — PRUEBA]')];
    }

    /** BILLING_PROFORMA_ANTICIPADA y los días de anticipo, sin abrir el .env. */
    private function proformaAnticipada(): array
    {
        $propios = Schema::hasColumn('billing_configurations', 'envio_prefactura_dias')
            ? DB::table('billing_configurations')->whereNotNull('envio_prefactura_dias')
                ->selectRaw('count(*) as n, min(envio_prefactura_dias) as minimo, max(envio_prefactura_dias) as maximo')->first()
            : null;
        $creacion = DB::table('config_finance_notifications')->where('type_config', 'proforma_invoice')->orderBy('id')->value('auto_send_notifications');

        return [
            'encendido' => (bool) config('billing.proforma_anticipada'),
            'dias' => EnvioPrefactura::diasPorDefecto(),
            'clientes_con_valor_propio' => (int) ($propios->n ?? 0),
            'rango_propio' => ($propios->n ?? 0) ? [(int) $propios->minimo, (int) $propios->maximo] : null,
            'creacion_automatica' => $creacion === null ? null : (bool) $creacion,
        ];
    }
}
