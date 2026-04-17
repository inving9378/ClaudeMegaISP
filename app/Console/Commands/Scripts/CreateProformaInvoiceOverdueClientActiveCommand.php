<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Finance\Invoice\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CreateProformaInvoiceOverdueClientActiveCommand extends Command
{
    protected $signature = 'invoice:create-proformas-atrasadas-clientes-activos
                            {--client-id= : ID específico del cliente a procesar}
                            {--all-clients : Procesar todos los clientes activos}';

    protected $description = 'Crea facturas proformas pagadas con carácter retroactivo para todos los períodos desde la creación del cliente';

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
        $this->info('Iniciando creación de proformas retroactivas...');

        try {
            $clientId = $this->option('client-id');
            $allClients = $this->option('all-clients');

            // Validar que se pase al menos una opción
            if (!$clientId && !$allClients) {
                $this->error('Debe especificar --client-id=ID o --all-clients');
                return 1;
            }

            // Obtener clientes según la opción seleccionada
            if ($clientId) {
                $clientes = Client::where('id', $clientId)
                    ->whereHas('client_main_information')
                    ->active()
                    ->get();

                if ($clientes->isEmpty()) {
                    $this->error("Cliente con ID {$clientId} no encontrado o no activo");
                    return 1;
                }
            } else {
                $clientes = Client::whereHas('client_main_information')
                    ->active()
                    ->get();
            }

            if ($clientes->isEmpty()) {
                $this->info("No hay clientes para procesar.");
                return;
            }

            $contadorClientes = 0;
            $contadorFacturas = 0;

            foreach ($clientes as $cliente) {
                try {
                    $this->info("Procesando cliente ID: {$cliente->id}, Nombre: {$cliente->name}");

                    $facturasCreadas = $this->createRetroactiveProformasForClient($cliente);
                    $contadorFacturas += $facturasCreadas;

                    if ($facturasCreadas > 0) {
                        $contadorClientes++;
                    }

                    $this->info("Cliente {$cliente->id}: {$facturasCreadas} facturas creadas");
                } catch (\Exception $e) {
                    Log::error("Error creando proformas retroactivas para cliente {$cliente->id}: " . $e->getMessage());
                    $this->error("Error con cliente {$cliente->id}: " . $e->getMessage());
                }
            }

            $this->info("Proceso completado. Se crearon {$contadorFacturas} proformas para {$contadorClientes} clientes.");
        } catch (\Exception $e) {
            Log::error('Error en comando de proformas retroactivas: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function createRetroactiveProformasForClient(Client $cliente)
    {
        $clientRepository = new ClientRepository();
        $montos = $clientRepository->calculateAmounts($cliente);

        $fechaCreacionCliente = $cliente->created_at;
        $today = Carbon::now();
        $periodoActual = $today->format('Y-m');

        // Obtener el primer período (mes y año de creación del cliente)
        $primerPeriodo = Carbon::parse($fechaCreacionCliente)->format('Y-m');

        // Generar todos los períodos desde la creación hasta el actual (excluyendo el actual)
        $periodos = $this->generatePeriods($primerPeriodo, $periodoActual);

        if (empty($periodos)) {
            $this->info("Cliente {$cliente->id}: No hay períodos para procesar");
            return 0;
        }

        $this->info("Períodos a procesar para cliente {$cliente->id}: " . implode(', ', $periodos));

        $facturasCreadas = 0;

        foreach ($periodos as $periodo) {
            // Verificar si ya existe una factura para este período
            $facturaExistente = Invoice::where('client_id', $cliente->id)
                ->where('period', $periodo)
                ->exists();

            if (!$facturaExistente) {
                $this->createProformaForPeriod($cliente, $montos, $periodo);
                $facturasCreadas++;
            } else {
                $this->info("Factura para período {$periodo} ya existe, omitiendo...");
            }
        }

        return $facturasCreadas;
    }

    private function generatePeriods(string $startPeriod, string $endPeriod): array
    {
        $periods = [];
        $current = Carbon::createFromFormat('Y-m', $startPeriod);
        $end = Carbon::createFromFormat('Y-m', $endPeriod);

        // Excluir el período actual (endPeriod)
        while ($current < $end) {
            $periods[] = $current->format('Y-m');
            $current->addMonth();
        }

        return $periods;
    }

    private function createProformaForPeriod(Client $cliente, array $montos, string $periodo)
    {
        // Calcular la fecha de vencimiento (25 del mes del período)
        $fechaVencimiento = Carbon::createFromFormat('Y-m', $periodo)
            ->day(self::PROFORMA_CREATION_DAY);

        // Si la fecha de creación del cliente es posterior al vencimiento, usar fecha de creación
        if ($cliente->created_at > $fechaVencimiento) {
            $fechaVencimiento = $cliente->created_at;
        }

        $data = [
            'number' => $this->invoiceService->generateRetroactiveInvoiceNumber($cliente, $periodo),
            'client_id' => $cliente->id,
            'due_date' => $fechaVencimiento,
            'subtotal' => $montos['subtotal'],
            'tax' => $montos['tax'],
            'total' => $montos['total'],
            'pending_balance' => 0, // Factura pagada
            'status' => Invoice::STATUS_PAID, // Marcada como pagada
            'type' => Invoice::TYPE_PROFORMA,
            'period' => $periodo,
            'notes' => "Proforma pagada creada retroactivamente para el período {$periodo}",
            'created_by' => 0,
            'payment_date' => now(), // Fecha de pago actual
            'payment_method' => 'retroactive', // Método de pago especial
            'is_retroactive' => true // Marcar como retroactiva
        ];

        $proforma = $this->invoiceService->createProformaInvoice($data);

        $this->info("Proforma retroactiva creada para cliente {$cliente->id}, período {$periodo}: {$proforma->number}");

        return $proforma;
    }
}
