<?php

namespace App\Services\Finance\Invoice;

use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function createProformaInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create($data);
            $clientRepository = new ClientRepository();
            $dataServices = $clientRepository->calculateAmounts($invoice->client);
            $dataInvoice = [];
            foreach ($dataServices['services'] as $key => $value) {
                $dataInvoice[] = [
                    'invoice_id' => $invoice->id,
                    'modelable_type' => $value['service_class'],
                    'modelable_id' => $value['service_id'],
                    'name' => $value['service_name'],
                    'tax_rate' => $value['iva_porcent'],
                    'tax_amount' => $value['iva'],
                    'total' => ($value['iva'] + $value['monto']),
                    'subtotal' => $value['monto']
                ];
            }
            if (!empty($dataInvoice)) {
                foreach ($dataInvoice as $dataItem) {
                    $this->createItemInvoice($dataItem);
                }
            }

            return $invoice;
        });
    }

    public function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');

        // Usar microtime() con prefijo único del proceso + random
        $microtime = substr(str_replace('.', '', microtime(true)), -8);
        $processId = getmypid() % 1000; // ID del proceso
        $random = random_int(1000, 9999); // Random de 4 dígitos

        $uniqueId = $processId . $microtime . $random;

        // Si es demasiado largo, acortarlo
        if (strlen($uniqueId) > 12) {
            $uniqueId = substr($uniqueId, -12);
        }

        return "PROF-{$year}{$month}-" . str_pad($uniqueId, 12, '0', STR_PAD_LEFT);
    }

    public function generateRetroactiveInvoiceNumber(Client $cliente, string $periodo): string
    {
        $clientId = str_pad($cliente->id, 6, '0', STR_PAD_LEFT);
        $micro = substr(microtime(false), 2, 6);
        $random = random_int(100, 999);

        // Combinamos todo para hacerlo único
        $uniquePart = $clientId . $micro . $random;

        // Si es demasiado largo, hacemos hash
        if (strlen($uniquePart) > 20) {
            $uniquePart = substr(md5($uniquePart), 0, 12);
        }

        return "RETRO-{$periodo}-{$uniquePart}";
    }


    public function updateProformaInvoicePendingDespuesDeUnPago($client, $payment, $transaction = null)
    {
        $client->refresh();

        // Normalizar periodos
        $periods = is_array($payment->payment_period)
            ? $payment->payment_period
            : explode(',', $payment->payment_period);

        // Buscar facturas existentes de esos periodos
        $existingInvoices = Invoice::proforma()
            ->where('client_id', $client->id)
            ->whereIn('period', $periods)
            ->where('status','!=', Invoice::STATUS_PAID)
            ->get()
            ->keyBy('period');

        $facturasProcesadas = [];
        $rest = $payment->amount;

        foreach ($periods as $period) {
            //OBTENER O CREAR FACTURA
            if (isset($existingInvoices[$period])) {
                $invoice = $existingInvoices[$period];
            } else {
                // Crear factura proforma para el periodo faltante
                $invoice = $this->crearNuevaFacturaProforma(
                    $client,
                    $period
                );
            }

            // 2️ APLICAR PAGO SEGÚN MONTO DISPONIBLE
            if ($rest <= 0) {
                break;
            }

            $pendingBalance = $invoice->pending_balance;

            if ($rest >= $pendingBalance) {
                // Pago completo
                $invoice->pending_balance = 0;
                $invoice->status = Invoice::STATUS_PAID;
                $invoice->payment_date = now();
                $rest -= $pendingBalance;
            } else {
                // Pago parcial
                $invoice->pending_balance = $pendingBalance - $rest;
                $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
                $rest = 0;
            }

            // 3️ VINCULAR PAGO
            $invoice->payment_id = $payment->id;
            $invoice->transaction_id = $transaction ? $transaction->id : null;
            $invoice->payment_method = $payment->payment_method_id;
            $invoice->save();
            $facturasProcesadas[] = $invoice;
        }

        return [
            'facturas_procesadas' => $facturasProcesadas,
            'facturas_creadas' => collect($facturasProcesadas)
                ->filter(fn($i) => $i->wasRecentlyCreated ?? false)
                ->count(),
            'credito_restante' => $rest,
            'mensaje' => 'Pago aplicado a los periodos seleccionados. Facturas faltantes creadas automáticamente.'
        ];
    }

    private function crearNuevaFacturaProforma(Client $client, $period = null): Invoice
    {
        $client->refresh();
        $clientRepository = new ClientRepository();
        $montos = $clientRepository->calculateAmounts($client);

        $data = [
            'number' => $this->generateInvoiceNumber(),
            'client_id' => $client->id,
            'due_date' => $client->fecha_corte,
            'subtotal' => $montos['subtotal'],
            'tax' => $montos['tax'],
            'total' => $montos['total'],
            'pending_balance' => 0,
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_PROFORMA,
            'notes' => "Factura creada automáticamente por pago anticipado",
            'created_by' => auth()->user()->id ?? 0,
            'period' => $period ? $period : now()->format('Y-m')
        ];

        return $this->createProformaInvoice($data);
    }

    private function crearNuevaFacturaProformaPagada(Client $client, Carbon $fechaVencimiento, $payment, $transaction, $period = null): Invoice
    {
        $client->refresh();
        $clientRepository = new ClientRepository();
        $montos = $clientRepository->calculateAmounts($client);

        $data = [
            'number' => $this->generateInvoiceNumber(),
            'client_id' => $client->id,
            'due_date' => $client->fecha_corte,
            'subtotal' => $montos['subtotal'],
            'tax' => $montos['tax'],
            'total' => $montos['total'],
            'pending_balance' => 0,
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_PROFORMA,
            'notes' => "Factura creada automáticamente por pago anticipado",
            'payment_id' => $payment->id,
            'transaction_id' => $transaction->id,
            'created_by' => auth()->user()->id ?? 0,
            'period' => $period ? $period : now()->format('Y-m')
        ];

        return $this->createProformaInvoice($data);
    }


    private function createItemInvoice($data)
    {
        InvoiceItem::create($data);
    }


    public function getPeriodByMonth($month)
    {
        $year = Carbon::now()->year;
        return $year . '-' . $month;
    }

    public function getStrPeriodByMonth($month)
    {
        try {
            $currentMonth = Carbon::create()->month($month);
            $mesActual = $currentMonth->locale('es')->monthName;
            $mesSiguiente = $currentMonth->copy()->addMonth()->locale('es')->monthName;
            return $mesActual . '-' . $mesSiguiente;
        } catch (\Exception $e) {
            return $month;
        }
    }

    public function getStrPeriodByMonthAndYear($period)
    {
        $date = Carbon::create()->parse($period);
        $mesActual = $date->locale('es')->monthName;
        $mesSiguiente = $date->copy()->addMonth()->locale('es')->monthName;
        return $mesActual . '-' . $mesSiguiente;
    }
}
