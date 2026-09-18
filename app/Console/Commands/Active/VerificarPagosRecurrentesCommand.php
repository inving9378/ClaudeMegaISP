<?php

namespace App\Console\Commands\Active;

use App\Models\TypeBilling;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Addons\Payments\Models\PaymentWebhookLog;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Modules\Core\Clientes\Services\BillingExpirationService;
use App\Modules\Core\Clientes\Services\BillingPaymentDateService;
use App\Modules\Core\Clientes\Services\ClientBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Verificación diaria de la salud de los pagos (item: pedido de Irving 2026-09-17, tras el fix
 * de fecha de corte en pagos tardíos de recurrentes/CUSTOM). Corre en la ventana 23:00-24:00
 * (Kernel.php), SOLO LECTURA — nunca escribe en `clients`/`payments`/`billing_configurations`.
 *
 * Cada chequeo:
 *  - `checkFechaPagoTardeInvariante` (RECURRENT/CUSTOM) y `checkDailyIndependienteDeNow` mutan
 *    en MEMORIA un cliente real ya cargado (fecha_corte/fecha_pago) — nunca llaman a
 *    $client->save(), así que no hay nada que reponer en BD.
 *  - `checkRecurrentSuspendidoPagoTarde` y `checkCustomCorteSincronizadoConPago` (2026-09-18)
 *    SÍ escriben (replican el flujo real de suspensión/removePeriodoGracia, que hace updates de
 *    verdad) — corren dentro de una transacción que SIEMPRE se revierte en su propio finally,
 *    nunca dejan nada persistido.
 *  - El de idempotencia de webhooks es 100% de solo lectura sobre datos reales.
 *
 * Si algún chequeo falla, crea (o reabre/actualiza) UN item en la Hoja de Ruta por chequeo,
 * dedupe por `auditor_fingerprint` (mismo mecanismo que AuditorService) para no duplicar
 * ticket cada noche mientras el mismo defecto siga sin resolverse.
 */
class VerificarPagosRecurrentesCommand extends Command
{
    protected $signature = 'pagos:verificar-recurrentes {--dry-run : No crea/actualiza tickets, solo reporta en consola}';

    protected $description = 'Verificación diaria de la salud de los pagos (fechas de corte, idempotencia de webhooks) — levanta ticket en la Torre de Control si algo falla';

    private const FINGERPRINT_PREFIX = 'pagos-verificacion-diaria';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checks = [
            'recurrent_pago_tarde' => fn () => $this->checkFechaPagoTardeInvariante(TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT, 'RECURRENT'),
            'custom_pago_tarde'    => fn () => $this->checkFechaPagoTardeInvariante(TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM, 'CUSTOM'),
            'daily_now_independiente' => fn () => $this->checkDailyIndependienteDeNow(),
            // 2026-09-18 — los dos de abajo replican el FLUJO REAL (suspensión/removePeriodoGracia),
            // no solo la fórmula aislada. Necesario: el invariante de arriba pasaba en aislado pese a
            // que el fix de RECURRENT no se disparaba en el camino real de un cliente ya suspendido
            // (fecha_corte se reescribe ANTES de que este método la lea) — un bug real que llegó a
            // producción (V1.35) sin que este comando lo cachara. Ver bitácora 2026-09-18.
            'recurrent_suspendido_pago_tarde' => fn () => $this->checkRecurrentSuspendidoPagoTarde(),
            'custom_corte_sincronizado_con_pago' => fn () => $this->checkCustomCorteSincronizadoConPago(),
            // 2026-09-18 (bug distinto, encontrado en prod reparando los 30 clientes de la
            // regresión anterior): ClientBillingService::billingServicesByClient(), rama "no
            // tiene suficiente balance pero le cobro el servicio" (cron billing_service_command:
            // process), avanzaba fecha_pago sin nunca llamar a setNewFechaCorteForClient().
            'recurrent_balance_insuficiente_corte_avanza' => fn () => $this->checkRecurrentBalanceInsuficienteCorteAvanza(),
            'webhooks_sin_duplicado'  => fn () => $this->checkWebhooksSinAplicacionDuplicada(),
        ];

        $huboFallo = false;

        foreach ($checks as $key => $fn) {
            try {
                $resultado = $fn();
            } catch (\Throwable $e) {
                $resultado = ['ok' => false, 'detalle' => 'Excepción al correr el chequeo: ' . $e->getMessage()];
            }

            $ok = $resultado['ok'] ?? false;
            $detalle = $resultado['detalle'] ?? '(sin detalle)';

            if ($ok) {
                $this->info("[OK] {$key}: {$detalle}");
                continue;
            }

            $huboFallo = true;
            $this->error("[FALLO] {$key}: {$detalle}");

            Log::channel('single')->error('[pagos:verificar-recurrentes] chequeo falló', [
                'check' => $key,
                'detalle' => $detalle,
            ]);

            if (!$dryRun) {
                $this->levantarOActualizarTicket($key, $detalle);
            }
        }

        if (!$huboFallo) {
            $this->info('Todos los chequeos de pagos pasaron.');
        }

        return self::SUCCESS;
    }

    /**
     * Invariante de regresión para RECURRENT/CUSTOM (bug real corregido 2026-09-17, commits
     * 4f8806fb + f7d7f969): un pago que llega DESPUÉS de fecha_corte debe anclar el nuevo ciclo
     * a una fecha POSTERIOR (o igual) a la que hubiera dado un pago a tiempo. Antes del fix,
     * ambos escenarios daban exactamente la misma fecha — el atraso no se reflejaba.
     *
     * Se muta en memoria un cliente real ya cargado (nunca se guarda) y se restaura
     * Carbon::setTestNow() en el finally pase lo que pase.
     */
    private function checkFechaPagoTardeInvariante(int $typeOfBillingId, string $etiqueta): array
    {
        $client = Client::whereHas('client_main_information', function ($q) use ($typeOfBillingId) {
            $q->where('type_of_billing_id', $typeOfBillingId);
        })
            ->whereNotNull('fecha_corte')
            ->whereNotNull('fecha_pago')
            ->whereHas('billing_configuration')
            ->first();

        if (!$client) {
            return ['ok' => true, 'detalle' => "Sin cliente {$etiqueta} con fecha_corte+fecha_pago+billing_configuration en esta BD — chequeo omitido (nada que verificar)."];
        }

        $fechaCorteOriginal = $client->fecha_corte;
        $fechaPagoOriginal = $client->fecha_pago;

        try {
            $corte = Carbon::parse($fechaCorteOriginal);
            $service = new BillingPaymentDateService();

            Carbon::setTestNow($corte->copy());
            $client->fecha_corte = $fechaCorteOriginal;
            $client->fecha_pago = $fechaPagoOriginal;
            $aTiempo = Carbon::parse($service->getNewFechaPagoByClient($client, 1, false));

            Carbon::setTestNow($corte->copy()->addDays(5));
            $client->fecha_corte = $fechaCorteOriginal;
            $client->fecha_pago = $fechaPagoOriginal;
            $tarde = Carbon::parse($service->getNewFechaPagoByClient($client, 1, false));

            if ($tarde->lt($aTiempo)) {
                return [
                    'ok' => false,
                    'detalle' => "Cliente #{$client->id} ({$etiqueta}): pagar 5 días tarde dio una fecha de corte MENOR ({$tarde->toDateString()}) que pagar a tiempo ({$aTiempo->toDateString()}). "
                        . 'Esto es el bug de fecha_pago/fecha_corte ya corregido en BillingPaymentDateService (commits 4f8806fb/f7d7f969) — parece haber regresado.',
                ];
            }

            if ($tarde->eq($aTiempo)) {
                return [
                    'ok' => false,
                    'detalle' => "Cliente #{$client->id} ({$etiqueta}): pagar 5 días tarde dio EXACTAMENTE la misma fecha de corte ({$aTiempo->toDateString()}) que pagar a tiempo — el atraso no se está reflejando. Mismo bug de BillingPaymentDateService, posible regresión.",
                ];
            }

            return [
                'ok' => true,
                'detalle' => "Cliente #{$client->id} ({$etiqueta}): a tiempo={$aTiempo->toDateString()}, tarde(+5d)={$tarde->toDateString()} — el atraso sí se refleja (tarde > a tiempo).",
            ];
        } finally {
            Carbon::setTestNow();
            $client->fecha_corte = $fechaCorteOriginal;
            $client->fecha_pago = $fechaPagoOriginal;
        }
    }

    /**
     * Regresión del bug real de producción (2026-09-18, V1.35): el chequeo de arriba
     * (`checkFechaPagoTardeInvariante`) muta fecha_corte/fecha_pago EN MEMORIA sobre un cliente
     * intacto — pasa aunque el fix esté roto en el camino real, porque un cliente RECURRENT
     * realmente suspendido llega a BillingPaymentDateService con fecha_corte ya reescrita por
     * SuspendService (null) y luego por ClientRepository::removePeriodoGracia() (un valor nuevo
     * sin relación con el corte original) ANTES de que el guard la lea. Este chequeo SÍ replica
     * ese flujo completo con escrituras reales — por eso corre dentro de una transacción que
     * SIEMPRE se revierte en el finally, pase lo que pase (incluida una excepción a medio camino).
     */
    private function checkRecurrentSuspendidoPagoTarde(): array
    {
        $client = Client::whereHas('client_main_information', function ($q) {
            $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
        })
            ->whereNotNull('fecha_corte')
            ->whereNotNull('fecha_pago')
            ->whereHas('billing_configuration')
            ->first();

        if (!$client) {
            return ['ok' => true, 'detalle' => 'Sin cliente RECURRENT con fecha_corte+fecha_pago+billing_configuration — chequeo omitido.'];
        }

        DB::beginTransaction();

        try {
            $corteOriginal = $client->fecha_corte;
            $estadoOriginal = $client->client_main_information->estado;

            // 1) Suspensión real: fecha_corte -> null (SuspendService::ifClientChangeToBlockedRemoveDateCorte)
            //    + período de gracia (solo RECURRENT).
            $clientRepository = new ClientRepository();
            $clientRepository->removeFechaCorteById($client->id);
            $clientRepository->addPeriodoGracia($client);
            $client->client_main_information->estado = 'Bloqueado';
            $client->client_main_information->save();
            $client->refresh();

            // 2) Paga 5 días después del corte que aplicaba, DENTRO del período de gracia.
            $fechaPagoReal = Carbon::parse($corteOriginal)->addDays(5);
            Carbon::setTestNow($fechaPagoReal);

            // 3) billingForce(): removePeriodoGracia() SIEMPRE corre antes que el guard de fecha_pago.
            $clientRepository->removePeriodoGracia($client, true, 1);
            $client->refresh();

            $service = new BillingPaymentDateService();
            $resultado = $service->getNewFechaPagoByClient($client, 1, false);

            $esperado = $fechaPagoReal->copy()->addMonthsWithoutOverflow(1)->endOfDay()->toDateTimeString();

            if ($resultado !== $esperado) {
                return [
                    'ok' => false,
                    'detalle' => "Cliente #{$client->id}: un cliente RECURRENT suspendido (corte {$corteOriginal}) que paga el "
                        . "{$fechaPagoReal->toDateString()} dentro del período de gracia debería anclar su nueva fecha_pago a "
                        . "{$esperado}, pero dio {$resultado}. El guard de pago tardío no está detectando la suspensión real "
                        . '(revisar la señal estado=Bloqueado en BillingPaymentDateService, rama RECURRENT).',
                ];
            }

            return [
                'ok' => true,
                'detalle' => "Cliente #{$client->id}: flujo real de suspensión + pago tardío dentro del período de gracia ancla correctamente a {$resultado}.",
            ];
        } finally {
            Carbon::setTestNow();
            DB::rollBack();
        }
    }

    /**
     * Regresión del bug real de producción (2026-09-18, commit del fix de BillingExpirationService):
     * en CUSTOM, fecha_corte se calculaba desde fecha_corte_anterior + N meses, TOTALMENTE
     * desconectado de fecha_pago — un pago tardío podía dejar fecha_corte ANTES de la nueva
     * fecha_pago (suspensión prematura). Ahora fecha_corte deriva de fecha_pago + billing_expiration,
     * igual que RECURRENT. Este chequeo replica actionBilling()+setNewFechaCorteForClient() en
     * orden real, con escrituras reales revertidas siempre en el finally.
     */
    private function checkCustomCorteSincronizadoConPago(): array
    {
        $client = Client::whereHas('client_main_information', function ($q) {
            $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM);
        })
            ->whereNotNull('fecha_corte')
            ->whereNotNull('fecha_pago')
            ->whereHas('billing_configuration')
            ->first();

        if (!$client) {
            return ['ok' => true, 'detalle' => 'Sin cliente CUSTOM con fecha_corte+fecha_pago+billing_configuration — chequeo omitido.'];
        }

        DB::beginTransaction();

        try {
            $corte = Carbon::parse($client->fecha_corte);
            Carbon::setTestNow($corte->copy()->addDays(5)); // paga 5 días tarde

            $paymentService = new BillingPaymentDateService();
            $newFechaPago = $paymentService->getNewFechaPagoByClient($client, 1, false);
            $client->fecha_pago = $newFechaPago;
            $client->save();
            $client->refresh();

            $expirationService = new BillingExpirationService($client);
            $newFechaCorte = $expirationService->setNewFechaCorteForClient(null, 1);

            if (Carbon::parse($newFechaCorte)->lte(Carbon::parse($newFechaPago))) {
                return [
                    'ok' => false,
                    'detalle' => "Cliente #{$client->id}: tras un pago CUSTOM tardío, fecha_corte ({$newFechaCorte}) quedó ANTES O IGUAL "
                        . "que la nueva fecha_pago ({$newFechaPago}) — el cliente se suspendería antes de que le toque volver a pagar. "
                        . 'Revisar BillingExpirationService::getFechaCorteForBillingPrepaidCustom() (debe derivar de fecha_pago).',
                ];
            }

            return [
                'ok' => true,
                'detalle' => "Cliente #{$client->id}: fecha_corte ({$newFechaCorte}) queda después de la nueva fecha_pago ({$newFechaPago}).",
            ];
        } finally {
            Carbon::setTestNow();
            DB::rollBack();
        }
    }

    /**
     * Regresión del bug real de producción (2026-09-18, encontrado por Irving al reparar los
     * clientes de la regresión anterior): en `ClientBillingService::billingServicesByClient()`,
     * la rama "cliente RECURRENT sin saldo suficiente pero el cron lo cobra de todos modos"
     * (`billing_service_command:process`) llamaba a `actionBilling()` (avanza fecha_pago) pero
     * NUNCA a `setNewFechaCorteForClient()` — fecha_corte quedaba congelada mientras fecha_pago
     * seguía avanzando mes a mes, y el cron de suspensión terminaba bloqueando injustamente a un
     * cliente "al día" según su fecha_pago real (casos reales: #7408, #6861). Este chequeo fuerza
     * saldo insuficiente en memoria+BD (dentro de una transacción SIEMPRE revertida) y replica el
     * mismo `billingServicesByClient()` que corre el cron.
     */
    private function checkRecurrentBalanceInsuficienteCorteAvanza(): array
    {
        $client = Client::whereHas('client_main_information', function ($q) {
            $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
        })
            ->whereNotNull('fecha_corte')
            ->whereNotNull('fecha_pago')
            ->whereHas('billing_configuration')
            ->first();

        if (!$client) {
            return ['ok' => true, 'detalle' => 'Sin cliente RECURRENT con fecha_corte+fecha_pago+billing_configuration — chequeo omitido.'];
        }

        DB::beginTransaction();

        try {
            $corteAntes = $client->fecha_corte;

            // Fuerza saldo insuficiente: getCuantasVecesSeLePuedenCobrarLosServiciosActivos()
            // debe devolver null/0 para caer en la rama del bug.
            $client->load('balance');
            $client->balance->amount = -99999;
            $client->balance->save();

            $clientRepository = new ClientRepository();
            $veces = $clientRepository->getCuantasVecesSeLePuedenCobrarLosServiciosActivos($client);
            if ($veces) {
                return ['ok' => true, 'detalle' => "Cliente #{$client->id}: no se pudo forzar saldo insuficiente en este entorno — chequeo omitido."];
            }

            $billingService = new ClientBillingService();
            $billingService->billingServicesByClient($client, ClientBillingService::TYPE_BILLING_EXECUTED_PROCESS);
            $client->refresh();

            if ($client->fecha_corte === $corteAntes) {
                return [
                    'ok' => false,
                    'detalle' => "Cliente #{$client->id}: la rama 'sin saldo pero se cobra' avanzó fecha_pago ({$client->fecha_pago}) sin mover "
                        . "fecha_corte (sigue en {$corteAntes}) — el cliente quedaría con fecha_corte congelada mientras fecha_pago avanza, "
                        . 'expuesto a suspensión injusta. Revisar ClientBillingService::billingServicesByClient() (rama TYPE_BILLING_EXECUTED_PROCESS).',
                ];
            }

            return [
                'ok' => true,
                'detalle' => "Cliente #{$client->id}: fecha_corte avanzó junto con fecha_pago ({$corteAntes} → {$client->fecha_corte}).",
            ];
        } finally {
            DB::rollBack();
        }
    }

    /**
     * DAILY (fecha_pago + N días) no debe depender de Carbon::now() — a diferencia de
     * RECURRENT/CUSTOM, no hay "día fijo de facturación" que snapear, así que estructuralmente
     * no puede sufrir el mismo bug. Este chequeo es una red de regresión: si alguna vez alguien
     * mete un now() en esa rama, el resultado dejaría de ser estable entre dos "hoy" distintos.
     */
    private function checkDailyIndependienteDeNow(): array
    {
        $client = Client::whereHas('client_main_information', function ($q) {
            $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY);
        })
            ->whereNotNull('fecha_pago')
            ->first();

        if (!$client) {
            return ['ok' => true, 'detalle' => 'Sin cliente DAILY con fecha_pago en esta BD — chequeo omitido (nada que verificar).'];
        }

        $fechaPagoOriginal = $client->fecha_pago;

        try {
            $service = new BillingPaymentDateService();

            Carbon::setTestNow(Carbon::now()->subDays(3));
            $client->fecha_pago = $fechaPagoOriginal;
            $r1 = $service->getNewFechaPagoByClient($client, 1, false);

            Carbon::setTestNow(Carbon::now()->addDays(20));
            $client->fecha_pago = $fechaPagoOriginal;
            $r2 = $service->getNewFechaPagoByClient($client, 1, false);

            if ($r1 !== $r2) {
                return [
                    'ok' => false,
                    'detalle' => "Cliente #{$client->id} (DAILY): el resultado cambió según 'ahora' ({$r1} vs {$r2}) pese a tener el mismo fecha_pago de partida — la fórmula ya no es independiente de Carbon::now().",
                ];
            }

            return ['ok' => true, 'detalle' => "Cliente #{$client->id} (DAILY): resultado estable entre dos 'ahora' distintos ({$r1})."];
        } finally {
            Carbon::setTestNow();
            $client->fecha_pago = $fechaPagoOriginal;
        }
    }

    /**
     * Solo lectura sobre datos reales: ningún (provider, external_id) de webhook de pago debe
     * tener más de una fila con status='processed' — eso significaría que la misma transacción
     * del proveedor (SPEI/OpenPay) se aplicó dos veces al saldo de un cliente.
     */
    private function checkWebhooksSinAplicacionDuplicada(): array
    {
        $duplicados = PaymentWebhookLog::query()
            ->select('provider', 'external_id')
            ->selectRaw('COUNT(*) as veces')
            ->where('status', 'processed')
            ->whereNotNull('external_id')
            ->groupBy('provider', 'external_id')
            ->having('veces', '>', 1)
            ->limit(10)
            ->get();

        if ($duplicados->isEmpty()) {
            return ['ok' => true, 'detalle' => 'Sin (provider, external_id) con más de un webhook status=processed.'];
        }

        $lista = $duplicados->map(fn ($d) => "{$d->provider}:{$d->external_id} x{$d->veces}")->implode(', ');

        return [
            'ok' => false,
            'detalle' => "Webhooks de pago aplicados más de una vez (posible cobro/abono duplicado real): {$lista}",
        ];
    }

    private function levantarOActualizarTicket(string $checkKey, string $detalle): void
    {
        $fingerprint = substr(sha1(self::FINGERPRINT_PREFIX . '|' . $checkKey), 0, 40);

        $existente = RoadmapItem::where('auditor_fingerprint', $fingerprint)
            ->whereNotIn('estado_aprobacion', ['completado', 'rechazado', 'cancelado'])
            ->first();

        if ($existente) {
            $log = $existente->log ?? [];
            $log[] = [
                'ts' => now()->toIso8601String(),
                'por' => 'pagos:verificar-recurrentes',
                'evento' => 'fallo_repetido',
                'detalle' => $detalle,
            ];
            $existente->log = $log;
            $existente->comentarios_claude = "Última corrida fallida: " . now()->toDateTimeString() . ". {$detalle}";
            $existente->save();

            $this->warn("Ticket existente #{$existente->id} actualizado (sigue sin resolverse).");

            return;
        }

        $item = RoadmapItem::create([
            'title'             => '[Verificación de pagos] Falló: ' . $this->tituloLegible($checkKey),
            'description'       => $detalle,
            'modulo'            => 'Pagos / Verificación diaria',
            'status'            => 'pending',
            'priority'          => 'alta',
            'nivel_riesgo'      => 'B',
            'nivel_riesgo_origen' => 'interno',
            'estado_aprobacion' => 'requiere_irving',
            'prompt'            => "Este item lo generó la verificación diaria automática de pagos "
                . "(`pagos:verificar-recurrentes`, corre 23:00-24:00 vía Kernel.php).\n\n"
                . "CHEQUEO QUE FALLÓ: {$checkKey}\n\nDETALLE:\n{$detalle}\n\n"
                . "Es dinero real (facturación/pagos) — investigar la causa antes de tocar nada, "
                . "y NO cerrar este item hasta confirmar (con datos reales o simulados) que el "
                . "chequeo vuelve a pasar corriendo `php artisan pagos:verificar-recurrentes --dry-run`.",
            'comentarios_claude' => "Creado automáticamente por pagos:verificar-recurrentes el " . now()->toDateTimeString() . ".",
            'log' => [[
                'ts' => now()->toIso8601String(),
                'por' => 'pagos:verificar-recurrentes',
                'evento' => 'ticket_creado_por_fallo',
                'check' => $checkKey,
            ]],
        ]);

        $item->auditor_fingerprint = $fingerprint;
        $item->save();

        $this->warn("Ticket nuevo #{$item->id} creado en la Torre de Control.");
    }

    private function tituloLegible(string $checkKey): string
    {
        return match ($checkKey) {
            'recurrent_pago_tarde' => 'fecha de corte no se mueve con pago tardío (RECURRENT)',
            'custom_pago_tarde' => 'fecha de corte no se mueve con pago tardío (CUSTOM)',
            'recurrent_suspendido_pago_tarde' => 'un RECURRENT ya suspendido que paga tarde no ancla bien la fecha_pago',
            'custom_corte_sincronizado_con_pago' => 'fecha_corte quedó antes que la nueva fecha_pago en CUSTOM',
            'recurrent_balance_insuficiente_corte_avanza' => 'fecha_corte no avanzó cuando el cron cobra sin saldo suficiente (RECURRENT)',
            'daily_now_independiente' => 'DAILY dejó de ser independiente de la hora actual',
            'webhooks_sin_duplicado' => 'webhook de pago aplicado más de una vez',
            default => $checkKey,
        };
    }
}
