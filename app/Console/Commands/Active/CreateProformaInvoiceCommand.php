<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\ClientRepository;
use App\Modules\Core\Configuracion\Repositories\ConfigFinanceNotificationRepository;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientBillingPause;
use App\Models\Invoice;
use App\Services\Finance\Invoice\InvoiceService;
use App\Services\Finance\Billing\BillingDocumentService;
use App\Services\Finance\Invoice\EnvioPrefactura;
use App\Services\Finance\Invoice\ProformaAnticipada;
use App\Services\Finance\Billing\ReglasFacturacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CreateProformaInvoiceCommand extends Command
{
    protected $signature = 'invoice:create-proformas';
    protected $description = 'Crea facturas proformas según la configuración del sistema';

    protected $invoiceService;
    protected $configRepository;

    public function __construct(
        InvoiceService $invoiceService,
        ConfigFinanceNotificationRepository $configRepository
    ) {
        parent::__construct();
        $this->invoiceService = $invoiceService;
        $this->configRepository = $configRepository;
    }

    const PROFORMA_CREATION_DAY = 25;

    public function handle()
    {
        $this->info('Iniciando creación de proformas...');

        try {
            $config = $this->configRepository->getNotificationType('proforma_invoice');

            if (!$config || !$config->auto_send_notifications) {
                $this->info('La creación automática de proformas está desactivada.');
                return;
            }

            if (config('billing.proforma_anticipada')) {
                $this->crearAnticipadas();
                return;
            }

            $today = Carbon::now();

            // Determinar para qué período crear las proformas
            if ($today->day >= self::PROFORMA_CREATION_DAY) {
                $periodo = $today->copy()->addMonth()->format('Y-m');
                $primerDiaPeriodo = $today->copy()->addMonth()->startOfMonth();
                $ultimoDiaPeriodo = $today->copy()->addMonth()->endOfMonth();
            } else {
                $periodo = $today->format('Y-m');
                $primerDiaPeriodo = $today->copy()->startOfMonth();
                $ultimoDiaPeriodo = $today->copy()->endOfMonth();
            }

            $clientes = Client::whereHas('client_main_information', $this->sinCancelados())
                ->whereDate('fecha_corte', '>=', $primerDiaPeriodo->format('Y-m-d'))
                ->whereDate('fecha_corte', '<=', $ultimoDiaPeriodo->format('Y-m-d'))
                ->whereDoesntHave('invoices', function ($query) use ($periodo) {
                    $query->where('period', $periodo);
                })
                // Pausa de facturación programada (2026-09-24): mientras esté en_curso no se
                // le genera proforma — el job de la pausa la retoma normal al concluir.
                ->whereDoesntHave('billingPauses', function ($query) {
                    $query->where('estado', ClientBillingPause::ESTADO_EN_CURSO);
                })
                ->get();

            if ($clientes->isEmpty()) {
                $this->info("No hay clientes para crear proformas del período {$periodo}.");
                return;
            }

            $contador = 0;
            foreach ($clientes as $cliente) {
                try {
                    $this->createProformaForClient($cliente, $cliente->fecha_corte, $periodo);
                    $contador++;
                } catch (\Exception $e) {
                    Log::error("Error creando proforma para cliente {$cliente->id}: " . $e->getMessage());
                    $this->error("Error con cliente {$cliente->id}: " . $e->getMessage());
                }
            }

            $this->info("Proceso completado. Se crearon {$contador} proformas para el período {$periodo}.");
        } catch (\Exception $e) {
            Log::error('Error en comando de proformas: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * FAC-06 (#512) + FR-05 (#539, decisión de Irving 2026-10-17): cada cliente recibe su proforma
     * N días antes de SU día de facturación, con N = su «Envío prefactura» o, si no tiene, el valor
     * general (5). Día de facturación: fecha_pago en pago recurrente, fecha_corte en los demás
     * (EnvioPrefactura). El periodo y la fecha límite siguen siendo su fecha de corte, como la regla
     * de siempre. Sin retroactivo: un corte ya pasado no se recupera. Solo clientes Activos (FR-06);
     * mismas exclusiones (factura del periodo ya creada, pausa en curso) y misma creación idempotente.
     */
    private function crearAnticipadas(): void
    {
        $general = EnvioPrefactura::diasPorDefecto();
        $maxPropio = Schema::hasColumn('billing_configurations', 'envio_prefactura_dias')
            ? (int) DB::table('billing_configurations')->where('envio_prefactura_dias', '<=', ProformaAnticipada::MAX_DIAS)->max('envio_prefactura_dias')
            : 0;
        $hoy = Carbon::today();
        $desde = $hoy->toDateString();
        $hasta = $hoy->copy()->addDays(max($general, $maxPropio))->toDateString();

        // FR-06 (#540): en esta regla la prefactura solo es para clientes Activos; bloqueados e
        // inactivos reciben estado de cuenta (facturacion:estados-cuenta-mensuales) y Nuevo, nada.
        $clientes = Client::whereHas('client_main_information', fn ($q) => $q->where('estado', ReglasFacturacion::ACTIVO))
            ->where(function ($q) use ($desde, $hasta) {
                $q->where(fn ($w) => $w->whereDate('fecha_pago', '>=', $desde)->whereDate('fecha_pago', '<=', $hasta))
                    ->orWhere(fn ($w) => $w->whereDate('fecha_corte', '>=', $desde)->whereDate('fecha_corte', '<=', $hasta));
            })
            ->whereDoesntHave('billingPauses', function ($query) {
                $query->where('estado', ClientBillingPause::ESTADO_EN_CURSO);
            })
            ->get();

        $contador = 0;
        foreach ($clientes as $cliente) {
            $config = $cliente->billing_configuration;
            $facturacion = EnvioPrefactura::fechaDeFacturacion($cliente, $config);
            $corte = ProformaAnticipada::corte($cliente->fecha_corte);
            if (! $facturacion || ! $corte || $corte->lt($hoy)) {
                continue;
            }
            if (! ProformaAnticipada::tocaHoy($facturacion, EnvioPrefactura::diasDe($config, $general), $hoy)) {
                continue;
            }
            try {
                if ($this->createProformaForClient($cliente, $cliente->fecha_corte, ProformaAnticipada::periodo($corte))) {
                    $contador++;
                }
            } catch (\Exception $e) {
                Log::error("Error creando proforma anticipada para cliente {$cliente->id}: " . $e->getMessage());
                $this->error("Error con cliente {$cliente->id}: " . $e->getMessage());
            }
        }

        $this->info("Proforma anticipada (Envío prefactura: {$general} días antes del día de facturación, o los del cliente): se crearon {$contador} proformas.");
    }

    /** FR-02 (#536): un cliente Cancelado (baja definitiva) ya no recibe prefacturas. */
    private function sinCancelados(): \Closure
    {
        return fn ($q) => $q->where('estado', '!=', ReglasFacturacion::CANCELADO);
    }

    private function createProformaForClient(Client $cliente, $fechaCorte, string $periodo)
    {
        // Bloqueo de fila para prevenir crons simultáneos
        $cliente = Client::where('id', $cliente->id)->lockForUpdate()->first();

        // Revalidar si ya existe factura para este cliente + periodo
        $existe = Invoice::where('client_id', $cliente->id)
            ->where('period', $periodo)
            ->exists();

        if ($existe) {
            $this->info("Ya existe proforma para cliente {$cliente->id} en período {$periodo}, se omite.");
            return;
        }

        $clientRepository = new ClientRepository();
        $montos = $clientRepository->calculateAmounts($cliente);

        $data = [
            'number' => $this->invoiceService->generateInvoiceNumber(),
            'client_id' => $cliente->id,
            'due_date' => $fechaCorte,
            'subtotal' => $montos['subtotal'],
            'tax' => $montos['tax'],
            'total' => $montos['total'],
            'pending_balance' => $montos['total'],
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_PROFORMA,
            'period' => $periodo,
            'notes' => "Proforma correspondiente al período {$periodo}",
            'created_by' => 0
        ];

        $proforma = $this->invoiceService->createProformaInvoice($data);

        $this->info("Proforma creada para cliente {$cliente->id}: {$proforma->number}");

        // Genera PDF + registro BillingNotification en_espera (ventana ≥12 h desde billing_config).
        // Respeta billing_config.send_prefactura_email: si está en off, el registro se crea
        // pero send_by_email=false y el command de envío lo ignora.
        try {
            (new BillingDocumentService())->generarPrefactura($proforma);
        } catch (\Throwable $e) {
            Log::error("[CreateProformaInvoiceCommand] Error generando doc prefactura para invoice#{$proforma->id}: " . $e->getMessage());
        }

        return $proforma;
    }
}
