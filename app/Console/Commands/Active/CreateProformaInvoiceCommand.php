<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Finance\Invoice\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            $clientes = Client::whereHas('client_main_information')
                ->whereDate('fecha_corte', '>=', $primerDiaPeriodo->format('Y-m-d'))
                ->whereDate('fecha_corte', '<=', $ultimoDiaPeriodo->format('Y-m-d'))
                ->whereDoesntHave('invoices', function ($query) use ($periodo) {
                    $query->where('period', $periodo);
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

        return $proforma;
    }
}
