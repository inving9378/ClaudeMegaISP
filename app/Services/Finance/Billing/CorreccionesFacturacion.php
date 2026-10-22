<?php

namespace App\Services\Finance\Billing;

use App\Models\FacturacionCorreccion as Correccion;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Core\Clientes\Models\Client;
use App\Support\Seguridad\SuperAdministrador;
use Carbon\Carbon;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Item #571, Parte B (2026-10-22): correcciones de facturación sin comandos manuales en producción.
 *
 * Al terminar la actualización, la migración deja una corrida pendiente y
 * facturacion:correcciones-automaticas la toma una sola vez: clasifica las proformas abiertas
 * de TODOS los clientes del servidor donde corre (ClasificadorDeuda) y por cada uno decide:
 *  - se aplica sola (a) una FANTASMA cuya corrección no mueve dinero: sin montos de pago en duda y
 *    sin subir lo que el cliente debe; (b) un caso ya decidido (config billing.correcciones.decididas)
 *    si el reacomodo coincide EXACTO con el esperado;
 *  - queda en Finanzas › «Correcciones pendientes» todo lo demás: FANTASMA + DEBE, REVISAR, una
 *    fantasma que subiría la deuda, un caso decidido que no cuadra y los pagos cuyo monto no
 *    coincide con su estado de cuenta (como el del 05-07 del cliente 1286).
 * Cada corrección aplicada deja respaldo JSON, SQL para deshacer (también en la base) y su fila en
 * facturacion_correcciones, que es la bitácora. Idempotente: la corrida corre una vez, una
 * propuesta descartada o aplicada con los mismos datos no se vuelve a crear, y lo ya corregido
 * deja de clasificarse como fantasma.
 */
class CorreccionesFacturacion
{
    public const LOG = 'facturacion-correcciones';

    private const DIR = 'respaldos/facturacion';

    /** Mientras es true nada se aplica solo (recálculo pedido desde la pantalla). */
    private bool $soloProponer = false;

    public function __construct(private ClasificadorDeuda $clasificador, private ReacomodoPagos $reacomodo)
    {
    }

    // ─── Corridas ────────────────────────────────────────────────────────────

    /** Toma y corre las corridas pendientes (cada una una sola vez). */
    public function correrPendientes(): array
    {
        $resultados = [];
        foreach (DB::table('facturacion_corridas')->where('estado', 'pendiente')->orderBy('id')->pluck('id') as $id) {
            $tomada = DB::table('facturacion_corridas')->where('id', $id)->where('estado', 'pendiente')
                ->update(['estado' => 'en_proceso', 'iniciada_at' => now(), 'servidor' => gethostname() ?: null, 'updated_at' => now()]);
            if (! $tomada) {
                continue; // otra ejecución la tomó
            }
            try {
                $r = $this->correr($id, false);
                DB::table('facturacion_corridas')->where('id', $id)->update([
                    'estado' => 'terminada', 'terminada_at' => now(), 'resumen' => json_encode($r['resumen'], JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
                ]);
                activity(self::LOG)->withProperties(['corrida' => $id] + $r['resumen'])->log('Corrida de correcciones terminada');
                $resultados[$id] = $r;
            } catch (Throwable $e) {
                DB::table('facturacion_corridas')->where('id', $id)->update(['estado' => 'fallida', 'error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now()]);
                Log::error('[facturacion:correcciones] corrida ' . $id . ' fallida: ' . $e->getMessage());
                $resultados[$id] = ['resumen' => ['error' => $e->getMessage()], 'clientes' => []];
            }
        }

        return $resultados;
    }

    public function nuevaCorrida(string $descripcion): int
    {
        return DB::table('facturacion_corridas')->insertGetId([
            'clave' => 'manual-' . now()->format('Ymd-His') . '-' . substr(md5((string) microtime(true)), 0, 6),
            'descripcion' => $descripcion, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Revisa a todos los clientes con una proforma abierta y a los casos decididos. Con $simular
     * decide lo mismo pero no escribe nada.
     *
     * @return array{resumen: array<string, int>, clientes: array<int, array{cliente: int, categoria: string, decision: string, detalle: string}>}
     */
    public function correr(?int $corridaId, bool $simular): array
    {
        $hoy = Carbon::today();
        $decididas = collect((array) config('billing.correcciones.decididas', []))->keyBy('cliente');
        $tocadas = [];
        $clientes = [];

        foreach ($this->clasificador->poblacion(true, $decididas->keys()->all()) as $id) {
            try {
                $filas = $this->procesarCliente((int) $id, $decididas->get((int) $id), $hoy, $corridaId, $simular);
            } catch (Throwable $e) {
                Log::warning("[facturacion:correcciones] cliente {$id}: {$e->getMessage()}");
                $filas = [['cliente' => (int) $id, 'categoria' => '', 'decision' => 'error', 'detalle' => $e->getMessage(), 'clave' => null]];
            }
            foreach ($filas as $f) {
                if ($f['clave']) {
                    $tocadas[] = $f['clave'];
                }
                if ($f['decision'] !== 'nada') {
                    $clientes[] = $f;
                }
            }
        }

        $obsoletas = $simular ? 0 : $this->retirarObsoletas($tocadas, $corridaId);
        $resumen = collect($clientes)->countBy('decision')->all() + ['obsoletas' => $obsoletas];

        return ['resumen' => $resumen, 'clientes' => $clientes];
    }

    /** @return array<int, array{cliente: int, categoria: string, decision: string, detalle: string, clave: ?string}> */
    private function procesarCliente(int $id, ?array $decidida, Carbon $hoy, ?int $corridaId, bool $simular): array
    {
        $cliente = Client::find($id);
        if (! $cliente) {
            return [];
        }
        if ($decidida) {
            return $this->procesarDecidida($cliente, $decidida, $corridaId, $simular);
        }

        $r = $this->clasificador->clasificar($id, $hoy);
        $fila = fn (string $decision, string $detalle, ?string $clave) => ['cliente' => $id, 'categoria' => $r['categoria'], 'decision' => $decision, 'detalle' => $detalle, 'clave' => $clave];

        if ($r['categoria'] === ClasificadorDeuda::REVISAR) {
            $clave = "revision:{$id}";
            $d = $this->proponer($cliente, Correccion::REVISION, $clave, Correccion::PROPUESTA, $r['categoria'], [],
                ['detalle' => $r['detalle'], 'saldo' => $r['saldo'], 'fecha_corte' => $r['fecha_corte'], 'pagos_borrados' => $r['pagos_borrados']],
                [], null, null, 'Su ciclo ya está cubierto por la fecha de corte: una persona decide qué factura no se cobra.', $corridaId, $simular);

            return [$fila($d, $r['detalle'], $clave)];
        }
        if (! $r['propuesta']) {
            return [$fila('nada', '', null)];
        }

        $p = $r['propuesta'];
        $parametros = ['desde' => $p['desde'], 'pagos_desde' => $p['pagos_desde'], 'anular' => $p['anular'], 'motivo' => $p['motivo']];
        $clave = "reacomodo:{$id}";
        try {
            $plan = $this->planDe($cliente, $parametros);
        } catch (InvalidArgumentException $e) {
            $d = $this->proponer($cliente, Correccion::REVISION, "revision:{$id}", Correccion::PROPUESTA, $r['categoria'], $parametros,
                ['detalle' => $r['detalle'], 'saldo' => $r['saldo'], 'fecha_corte' => $r['fecha_corte']], [], null, null,
                'La corrección propuesta no cuadra con los datos: ' . $e->getMessage(), $corridaId, $simular);

            return [$fila($d, $e->getMessage(), "revision:{$id}")];
        }

        $filas = $this->proponerMontos($cliente, $plan, $r['categoria'], $corridaId, $simular);
        if (! $plan['cambios']) {
            return array_merge([$fila('sin_cambios', '', $clave)], $filas);
        }

        [$deudaAntes, $deudaDespues] = $this->reacomodo->deuda($plan);
        $motivosManual = array_filter([
            $r['categoria'] !== ClasificadorDeuda::FANTASMA ? 'además debe facturas vencidas' : null,
            $deudaDespues > $deudaAntes + 0.004 ? 'la corrección subiría lo que debe de ' . number_format($deudaAntes, 2) . ' a ' . number_format($deudaDespues, 2) : null,
            $this->reacomodo->montosPendientes($plan) ? 'hay un pago con monto en duda' : null,
            ! $this->automaticas() ? 'las correcciones automáticas están apagadas' : null,
        ]);
        $detalle = $motivosManual ? 'Revisar: ' . implode('; ', $motivosManual) . '.' : 'Fantasma sin dinero involucrado: no mueve pagos de monto ni sube la deuda.';

        if (! $motivosManual) {
            $d = $this->aplicarSola($cliente, Correccion::AUTOMATICA, $clave, $r['categoria'], $parametros, $plan, $detalle, $corridaId, $simular);
        } else {
            $d = $this->proponer($cliente, Correccion::REACOMODO, $clave, Correccion::PROPUESTA, $r['categoria'], $parametros,
                $this->filasDelPlan($plan), $this->extrasDelPlan($plan), $deudaAntes, $deudaDespues, $detalle, $corridaId, $simular);
        }

        return array_merge([$fila($d, $detalle, $clave)], $filas);
    }

    private function procesarDecidida(Client $cliente, array $d, ?int $corridaId, bool $simular): array
    {
        $parametros = ['desde' => $d['desde'], 'pagos_desde' => $d['pagos_desde'], 'anular' => array_map('intval', $d['anular']), 'motivo' => $d['motivo'], 'decidida' => $d['clave']];
        $clave = "reacomodo:{$cliente->id}";
        $fila = fn (string $decision, string $detalle, ?string $c = null) => ['cliente' => $cliente->id, 'categoria' => 'DECIDIDA', 'decision' => $decision, 'detalle' => $detalle, 'clave' => $c ?? $clave];

        try {
            $plan = $this->planDe($cliente, $parametros);
        } catch (InvalidArgumentException $e) {
            if ($this->decididaYaAplicada($d)) {
                return [$fila('ya_aplicada', 'El reacomodo decidido ya estaba aplicado.')];
            }
            $decision = $this->proponer($cliente, Correccion::REVISION, "revision:{$cliente->id}", Correccion::DECIDIDA, 'DECIDIDA', $parametros,
                ['detalle' => $e->getMessage()], [], null, null, 'El caso decidido no cuadra con los datos de este servidor: ' . $e->getMessage(), $corridaId, $simular);

            return [$fila($decision, $e->getMessage(), "revision:{$cliente->id}")];
        }

        $filas = $this->proponerMontos($cliente, $plan, 'DECIDIDA', $corridaId, $simular);
        if (! $plan['cambios']) {
            return array_merge([$fila('sin_cambios', '')], $filas);
        }

        $diferencias = $this->diferenciasConEsperado($plan, $d['esperado']);
        if (! $diferencias && $this->automaticas()) {
            $decision = $this->aplicarSola($cliente, Correccion::DECIDIDA, $clave, 'DECIDIDA', $parametros, $plan, 'Caso decidido: el resultado coincide exacto con lo esperado.', $corridaId, $simular);

            return array_merge([$fila($decision, 'Coincide con lo esperado.')], $filas);
        }

        [$deudaAntes, $deudaDespues] = $this->reacomodo->deuda($plan);
        $detalle = $diferencias ? 'El caso decidido no coincide con lo esperado: ' . implode('; ', $diferencias) . '.' : 'Las correcciones automáticas están apagadas.';
        $decision = $this->proponer($cliente, Correccion::REACOMODO, $clave, Correccion::DECIDIDA, 'DECIDIDA', $parametros,
            $this->filasDelPlan($plan), $this->extrasDelPlan($plan), $deudaAntes, $deudaDespues, $detalle, $corridaId, $simular);

        return array_merge([$fila($decision, $detalle)], $filas);
    }

    /** Factura → pago del plan contra lo esperado; lista vacía = coincide exacto. */
    private function diferenciasConEsperado(array $plan, array $esperado): array
    {
        $real = [];
        foreach ($plan['filas'] as $f) {
            $real[(int) $f['factura']->id] = $f['despues']['payment_id'] ? (int) $f['despues']['payment_id'] : null;
        }
        $diferencias = [];
        foreach ($esperado as $factura => $pago) {
            if (! array_key_exists((int) $factura, $real)) {
                $diferencias[] = "falta la factura {$factura}";
            } elseif ($real[(int) $factura] !== (int) $pago) {
                $diferencias[] = "la factura {$factura} quedaría con el pago " . ($real[(int) $factura] ?? 'ninguno') . " y se esperaba {$pago}";
            }
        }
        foreach (array_diff_key($real, array_map('intval', $esperado)) as $factura => $pago) {
            $diferencias[] = "la factura {$factura} no estaba en lo esperado";
        }
        if ($plan['sin_factura']->isNotEmpty()) {
            $diferencias[] = 'quedarían pagos sin factura: ' . $plan['sin_factura']->pluck('id')->implode(', ');
        }

        return $diferencias;
    }

    private function decididaYaAplicada(array $d): bool
    {
        $anuladas = DB::table('invoices')->whereIn('id', $d['anular'])->where('status', Invoice::STATUS_CANCELLED)->count() === count($d['anular']);
        $ligas = DB::table('invoices')->whereIn('id', array_keys($d['esperado']))->pluck('payment_id', 'id');
        foreach ($d['esperado'] as $factura => $pago) {
            if ((int) ($ligas[$factura] ?? 0) !== (int) $pago) {
                return false;
            }
        }

        return $anuladas;
    }

    /** Un pago cuyo monto no coincide con su estado de cuenta queda en la pantalla, con las dos opciones. */
    private function proponerMontos(Client $cliente, array $plan, string $categoria, ?int $corridaId, bool $simular): array
    {
        return array_map(fn ($m) => $this->proponerMonto($cliente, $m, $categoria, $corridaId, $simular), array_values($this->reacomodo->montosPendientes($plan)));
    }

    /** @param array{pago: int, en_pago: float, en_cuenta: float, fecha: string} $m */
    private function proponerMonto(Client $cliente, array $m, string $categoria, ?int $corridaId, bool $simular): array
    {
        $clave = "monto_pago:{$m['pago']}";
        $detalle = "El pago {$m['pago']} ({$m['fecha']}) dice " . number_format($m['en_pago'], 2) . ' y su estado de cuenta ' . number_format($m['en_cuenta'], 2) . '. Confirmar el monto real con el banco.';
        $decision = $this->proponer($cliente, Correccion::MONTO_PAGO, $clave, Correccion::PROPUESTA, $categoria, ['pago' => $m['pago']],
            $m + ['saldo' => (float) DB::table('balances')->where('balanceable_id', $cliente->id)->whereIn('balanceable_type', ReacomodoPagos::TIPOS_CLIENTE)->value('amount')],
            ['opciones' => [
                ['monto' => $m['en_cuenta'], 'efecto' => 'El pago pasa de ' . number_format($m['en_pago'], 2) . ' a ' . number_format($m['en_cuenta'], 2) . '; el saldo no cambia (ya contaba ' . number_format($m['en_cuenta'], 2) . ').'],
                ['monto' => $m['en_pago'], 'efecto' => 'Se agrega un movimiento de corrección por ' . number_format(abs($m['en_cuenta'] - $m['en_pago']), 2) . ' en el estado de cuenta; el saldo a favor ' . ($m['en_cuenta'] > $m['en_pago'] ? 'baja' : 'sube') . ' en esa cantidad.'],
            ]],
            null, null, $detalle, $corridaId, $simular);

        return ['cliente' => $cliente->id, 'categoria' => $categoria, 'decision' => $decision, 'detalle' => $detalle, 'clave' => $clave];
    }

    // ─── Propuestas y aplicación ─────────────────────────────────────────────

    /**
     * Deja (o actualiza) la propuesta pendiente de esa clave. Si una igual —misma clave, mismos
     * datos— ya se aplicó o se descartó, no se vuelve a proponer.
     */
    private function proponer(Client $cliente, string $tipo, string $clave, string $origen, ?string $categoria, array $parametros,
        array $antes, array $despues, ?float $deudaAntes, ?float $deudaDespues, string $detalle, ?int $corridaId, bool $simular): string
    {
        $huella = $this->huella($cliente->id, $tipo, $parametros);
        if (Correccion::where('clave', $clave)->where('huella', $huella)->whereIn('estado', [Correccion::APLICADA, Correccion::DESCARTADA])->exists()) {
            return 'ya_resuelta';
        }
        if ($simular) {
            return 'pendiente';
        }

        $datos = compact('tipo', 'origen', 'categoria', 'parametros', 'antes', 'despues', 'detalle', 'huella') + [
            'client_id' => $cliente->id, 'corrida_id' => $corridaId, 'deuda_antes' => $deudaAntes, 'deuda_despues' => $deudaDespues,
        ];
        $existente = Correccion::where('clave', $clave)->where('estado', Correccion::PENDIENTE)->first();
        if ($existente) {
            $existente->update($datos);

            return 'pendiente';
        }
        Correccion::create($datos + ['clave' => $clave, 'estado' => Correccion::PENDIENTE]);

        return 'pendiente';
    }

    private function aplicarSola(Client $cliente, string $origen, string $clave, string $categoria, array $parametros, array $plan, string $detalle, ?int $corridaId, bool $simular): string
    {
        $huella = $this->huella($cliente->id, Correccion::REACOMODO, $parametros);
        if ($simular) {
            return 'se_aplicaria_sola';
        }
        [$deudaAntes, $deudaDespues] = $this->reacomodo->deuda($plan);
        $c = Correccion::where('clave', $clave)->where('estado', Correccion::PENDIENTE)->first() ?? new Correccion(['clave' => $clave]);
        $c->fill([
            'client_id' => $cliente->id, 'corrida_id' => $corridaId, 'tipo' => Correccion::REACOMODO, 'origen' => $origen, 'categoria' => $categoria,
            'parametros' => $parametros, 'antes' => $this->filasDelPlan($plan), 'despues' => $this->extrasDelPlan($plan),
            'deuda_antes' => $deudaAntes, 'deuda_despues' => $deudaDespues, 'detalle' => $detalle, 'huella' => $huella, 'estado' => Correccion::PENDIENTE,
        ])->save();

        $this->aplicar($c, $cliente, fn () => $this->planDe($cliente, $parametros), null);

        return 'aplicada_sola';
    }

    /**
     * Escribe la corrección: respaldo JSON, y dentro de una transacción con el cliente bloqueado
     * vuelve a planear y exige los mismos datos (huella) antes de tocar nada.
     */
    private function aplicar(Correccion $c, Client $cliente, Closure $planear, ?User $user): void
    {
        $base = self::DIR . "/correccion-{$c->id}-cliente-{$cliente->id}-" . now()->format('Ymd-His');
        $respaldo = $this->reacomodo->respaldo($cliente, ['origen' => 'facturacion_correcciones', 'correccion' => $c->id, 'parametros' => $c->parametros]);
        Storage::disk('local')->put($base . '.json', json_encode($respaldo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $deshacer = DB::transaction(function () use ($c, $cliente, $planear) {
            Client::where('id', $cliente->id)->lockForUpdate()->first();
            if ($this->huella($cliente->id, $c->tipo, $c->parametros) !== $c->huella) {
                throw new InvalidArgumentException('Los datos del cliente cambiaron mientras se aplicaba; no se escribió nada.');
            }
            $plan = $planear();
            $motivo = ($c->parametros['motivo'] ?? 'Corrección de facturación (item #571)') . " [corrección #{$c->id}]";

            return $this->reacomodo->aplicar($cliente, $plan, $motivo);
        });

        $sql = implode("\n", $deshacer) . "\n";
        Storage::disk('local')->put($base . '-deshacer.sql', $sql);
        $c->update(['estado' => Correccion::APLICADA, 'resuelta_por' => $user?->id, 'resuelta_at' => now(), 'respaldo_path' => 'storage/app/' . $base . '.json', 'deshacer_sql' => $sql]);

        $log = activity(self::LOG)->performedOn($c)->withProperties(['cliente' => $cliente->id, 'tipo' => $c->tipo, 'origen' => $c->origen, 'parametros' => $c->parametros]);
        if ($user) {
            $log->causedBy($user);
        }
        $log->log($user ? 'Corrección aprobada y aplicada' : 'Corrección aplicada sola al actualizar');
    }

    /** Aprobar desde la pantalla. $monto solo para monto_pago (una de sus dos opciones). */
    public function aprobar(Correccion $c, User $user, ?float $monto = null): Correccion
    {
        $this->exigirPermiso($user);
        if ($c->estado !== Correccion::PENDIENTE) {
            throw new InvalidArgumentException('Esta corrección ya no está pendiente.');
        }
        if ($c->tipo === Correccion::REVISION) {
            throw new InvalidArgumentException('Esta revisión no trae una corrección para aplicar: descártala cuando la hayas atendido.');
        }
        $cliente = Client::findOrFail($c->client_id);

        if ($this->huella($cliente->id, $c->tipo, $c->parametros) !== $c->huella) {
            $c->update(['estado' => Correccion::OBSOLETA, 'motivo' => 'Los datos cambiaron después de la propuesta.', 'resuelta_at' => now()]);
            if ($c->tipo === Correccion::MONTO_PAGO) {
                $pago = DB::table('payments')->where('id', $c->parametros['pago'] ?? 0)->whereNull('deleted_at')->first(['id', 'amount', 'date']);
                $mov = $pago ? $this->reacomodo->movimientoDelPago($pago->id) : null;
                if ($pago && $mov && abs((float) $mov->credit - (float) $pago->amount) > 0.004) {
                    $this->proponerMonto($cliente, ['pago' => $pago->id, 'en_pago' => round((float) $pago->amount, 2), 'en_cuenta' => round((float) $mov->credit, 2), 'fecha' => substr((string) $pago->date, 0, 10)], $c->categoria ?? '', null, false);
                }
                throw new InvalidArgumentException('El pago cambió después de la propuesta. Se volvió a calcular: revisa la nueva.');
            }
            $decidida = collect((array) config('billing.correcciones.decididas', []))->firstWhere('cliente', $cliente->id);
            $this->soloProponer = true;
            try {
                $this->procesarCliente($cliente->id, $decidida, Carbon::today(), null, false);
            } finally {
                $this->soloProponer = false;
            }
            throw new InvalidArgumentException('Los datos del cliente cambiaron después de la propuesta. Se volvió a calcular: revisa la nueva.');
        }

        if ($c->tipo === Correccion::MONTO_PAGO) {
            $opciones = array_map(fn ($o) => round((float) $o['monto'], 2), $c->despues['opciones'] ?? []);
            $monto = $monto === null ? null : round($monto, 2);
            if ($monto === null || ! in_array($monto, $opciones, true)) {
                throw new InvalidArgumentException('Elige el monto confirmado con el banco.');
            }
            $pago = (int) $c->parametros['pago'];
            $c->update(['parametros' => $c->parametros + ['monto' => $monto, 'motivo' => "Monto del pago {$pago} confirmado con el banco: {$monto}"]]);
            $c->huella = $this->huella($cliente->id, $c->tipo, $c->parametros);
            $c->save();
            $this->aplicar($c, $cliente, fn () => $this->reacomodo->planSoloMontos($cliente, [$pago => $monto]), $user);
        } else {
            $this->aplicar($c, $cliente, fn () => $this->planDe($cliente, $c->parametros), $user);
        }

        return $c->fresh();
    }

    public function descartar(Correccion $c, User $user, string $motivo): Correccion
    {
        $this->exigirPermiso($user);
        if ($c->estado !== Correccion::PENDIENTE) {
            throw new InvalidArgumentException('Esta corrección ya no está pendiente.');
        }
        $c->update(['estado' => Correccion::DESCARTADA, 'resuelta_por' => $user->id, 'resuelta_at' => now(), 'motivo' => $motivo]);
        activity(self::LOG)->causedBy($user)->performedOn($c)->withProperties(['cliente' => $c->client_id, 'tipo' => $c->tipo, 'motivo' => $motivo])->log('Corrección descartada');

        return $c->fresh();
    }

    // ─── Apoyo ───────────────────────────────────────────────────────────────

    private function automaticas(): bool
    {
        return ! $this->soloProponer && (bool) config('billing.correcciones.automaticas', true);
    }

    private function planDe(Client $cliente, array $p): array
    {
        return $this->reacomodo->planear($cliente, $p['desde'], $p['pagos_desde'], array_map('intval', $p['anular'] ?? []), []);
    }

    /**
     * Lo que pesa para decidir. Un monto de pago depende solo de ese pago y su movimiento (un
     * reacomodo del mismo cliente no lo cambia); un reacomodo o una revisión, de las facturas,
     * pagos y estado de cuenta del cliente, más los parámetros.
     */
    public function huella(int $clienteId, string $tipo, ?array $parametros): string
    {
        if ($tipo === Correccion::MONTO_PAGO) {
            $pagoId = (int) ($parametros['pago'] ?? 0);

            return hash('sha256', json_encode([$tipo, $pagoId,
                DB::table('payments')->where('id', $pagoId)->whereNull('deleted_at')->value('amount'),
                DB::table('transactions')->where('payment_id', $pagoId)->where('is_payment', 1)->whereNull('deleted_at')->orderBy('id')->pluck('credit', 'id'),
            ]));
        }
        $facturas = DB::table('invoices')->where('client_id', $clienteId)->whereNull('deleted_at')->where('type', Invoice::TYPE_PROFORMA)
            ->orderBy('id')->get(['id', 'period', 'status', 'pending_balance', 'payment_id', 'total']);
        $pagos = DB::table('payments')->where('paymentable_id', $clienteId)->whereIn('paymentable_type', ReacomodoPagos::TIPOS_CLIENTE)
            ->whereNull('deleted_at')->orderBy('id')->get(['id', 'amount', 'date']);
        $movimientos = DB::table('transactions')->whereIn('payment_id', $pagos->pluck('id'))->where('is_payment', 1)->whereNull('deleted_at')
            ->orderBy('id')->get(['id', 'payment_id', 'credit']);
        // Ordenadas: la columna JSON de MySQL reordena las llaves al guardar.
        $param = collect($parametros ?? [])->except(['monto', 'motivo'])->sortKeys()->all();

        return hash('sha256', json_encode([$tipo, $facturas, $pagos, $movimientos, $param]));
    }

    private function filasDelPlan(array $plan): array
    {
        $filas = [];
        foreach ($plan['filas'] as $f) {
            $fa = $f['factura'];
            $filas[] = [
                'factura' => $fa->id, 'periodo' => $fa->period, 'vence' => substr((string) $fa->due_date, 0, 10), 'total' => (float) $fa->total,
                'antes' => ['estado' => $fa->getOriginal('status'), 'pago' => $fa->getOriginal('payment_id'), 'pendiente' => (float) $fa->getOriginal('pending_balance')],
                'despues' => ['estado' => $f['despues']['status'], 'pago' => $f['despues']['payment_id'], 'pendiente' => (float) $f['despues']['pending_balance']],
                'pago_fecha' => $f['pago'] ? substr((string) $f['pago']->date, 0, 10) : null, 'monto' => (float) $f['monto'], 'cambia' => (bool) $f['cambia'],
            ];
        }
        foreach ($plan['anuladas'] as $fa) {
            $filas[] = [
                'factura' => $fa->id, 'periodo' => $fa->period, 'vence' => substr((string) $fa->due_date, 0, 10), 'total' => (float) $fa->total,
                'antes' => ['estado' => $fa->getOriginal('status'), 'pago' => $fa->getOriginal('payment_id'), 'pendiente' => (float) $fa->getOriginal('pending_balance')],
                'despues' => ['estado' => Invoice::STATUS_CANCELLED, 'pago' => null, 'pendiente' => 0.0],
                'pago_fecha' => null, 'monto' => 0.0, 'cambia' => true, 'anula' => true,
            ];
        }

        return $filas;
    }

    private function extrasDelPlan(array $plan): array
    {
        return [
            'sin_factura' => $plan['sin_factura']->map(fn ($p) => ['pago' => $p->id, 'fecha' => substr((string) $p->date, 0, 10), 'monto' => (float) $p->amount])->values()->all(),
            'montos_pendientes' => array_values($this->reacomodo->montosPendientes($plan)),
        ];
    }

    /**
     * Propuestas pendientes que ya no aplican: un reacomodo o una revisión que la corrida no volvió
     * a encontrar (se resolvió por otro lado), o un monto de pago que ya coincide con su estado de
     * cuenta. Un monto en duda sigue pendiente aunque su cliente ya no sea fantasma.
     */
    private function retirarObsoletas(array $tocadas, ?int $corridaId): int
    {
        $n = 0;
        $retirar = function (Correccion $c, string $motivo) use (&$n) {
            $c->update(['estado' => Correccion::OBSOLETA, 'resuelta_at' => now(), 'motivo' => $motivo]);
            $n++;
        };
        $pendientes = Correccion::where('estado', Correccion::PENDIENTE)->get();
        foreach ($pendientes->where('tipo', '!=', Correccion::MONTO_PAGO)->whereNotIn('clave', array_unique($tocadas)) as $c) {
            $retirar($c, 'Ya no aplica en la revisión' . ($corridaId ? " de la corrida {$corridaId}" : '') . '.');
        }
        foreach ($pendientes->where('tipo', Correccion::MONTO_PAGO) as $c) {
            $pago = DB::table('payments')->where('id', $c->parametros['pago'] ?? 0)->whereNull('deleted_at')->first(['id', 'amount']);
            $mov = $pago ? $this->reacomodo->movimientoDelPago($pago->id) : null;
            if (! $pago || ! $mov || abs((float) $mov->credit - (float) $pago->amount) <= 0.004) {
                $retirar($c, $pago ? 'El monto del pago ya coincide con su estado de cuenta.' : 'El pago ya no existe.');
            }
        }

        return $n;
    }

    private function exigirPermiso(User $user): void
    {
        if (! SuperAdministrador::es($user)) {
            throw new AuthorizationException('Solo un super-administrador puede aprobar o descartar correcciones de facturación.');
        }
    }
}
