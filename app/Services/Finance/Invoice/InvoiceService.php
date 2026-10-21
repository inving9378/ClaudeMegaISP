<?php

namespace App\Services\Finance\Invoice;

use App\Http\Repository\ClientRepository;
use App\Modules\Addons\Payments\Services\PaymentReferenceService;
use App\Modules\Core\Clientes\Models\Client;
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
            // Red de seguridad: todo cliente facturado tiene su referencia MEG
            // (cubre a quienes nacieron por import sin disparar el observer).
            if (!empty($invoice->client_id)) {
                PaymentReferenceService::ensureFor($invoice->client_id);
            }
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


    /**
     * Item #571: de los periodos dados, los que ya tienen su proforma pagada y ninguna abierta.
     * Pagar otra vez un periodo así creaba una segunda factura del mismo periodo.
     *
     * @return array<string, string> periodo => número de la factura que ya lo pagó
     */
    public function periodosYaPagados($client, $periods): array
    {
        $periods = $this->normalizarPeriodos($periods);
        if (empty($periods)) {
            return [];
        }

        $proformas = Invoice::proforma()
            ->where('client_id', $client->id)
            ->whereIn('period', $periods)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get()
            ->groupBy('period');

        $pagados = [];
        foreach ($proformas as $period => $facturas) {
            $abierta = $facturas->first(fn ($f) => $f->status !== Invoice::STATUS_PAID);
            $pagada = $facturas->first(fn ($f) => $f->status === Invoice::STATUS_PAID);
            if (! $abierta && $pagada) {
                $pagados[$period] = $pagada->number;
            }
        }

        return $pagados;
    }

    public function updateProformaInvoicePendingDespuesDeUnPago($client, $payment, $transaction = null)
    {
        $client->refresh();

        $periods = $this->normalizarPeriodos($payment->payment_period);

        $facturasProcesadas = [];
        $aplicados = [];
        $rest = $payment->amount;

        foreach ($periods as $period) {
            // Sin dinero no se crea ni se toca nada: antes se creaba la factura y luego se cortaba.
            if ($rest <= 0) {
                break;
            }

            // Item #571: un periodo ya pagado no recibe otra factura; el dinero pasa al siguiente
            // periodo sin pagar. La ventana de cobro propone meses seguidos a partir del primero
            // abierto y no sabe cuáles ya se pagaron fuera de orden.
            $period = $this->periodoSinPagarDesde($client, $period, $aplicados);
            $invoice = $this->facturaAbiertaDelPeriodo($client, $period)
                ?? $this->crearNuevaFacturaProforma($client, $period);
            $aplicados[] = $period;

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

        // Si algún periodo se movió, el pago dice dónde quedó su dinero (Finanzas lee este campo).
        if ($aplicados && $aplicados !== array_slice($periods, 0, count($aplicados))) {
            $payment->payment_period = implode(',', $aplicados);
            $payment->save();
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

    /**
     * Item #194(b) — saldado FIFO de deuda VIVA cuando el pago NO trae period
     * explícito (conciliación/SPEI, a diferencia del cajero que sí lo elige).
     * A diferencia de updateProformaInvoicePendingDespuesDeUnPago, NO crea
     * facturas de periodos faltantes (no hay period declarado para saber cuál
     * crear): solo reparte el monto contra las proformas de deuda viva ya
     * existentes, de la más antigua (period asc) a la más reciente.
     */
    public function saldarDeudaVivaFifo($client, $payment, $transaction = null): array
    {
        $client->refresh();

        $facturasPendientes = Invoice::proforma()
            ->where('client_id', $client->id)
            ->notPaid()
            ->where('pending_balance', '>', 0)
            ->orderBy('period', 'asc')
            ->get();

        $facturasProcesadas = [];
        $rest = $payment->amount;

        foreach ($facturasPendientes as $invoice) {
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

            $invoice->payment_id = $payment->id;
            $invoice->transaction_id = $transaction ? $transaction->id : null;
            $invoice->payment_method = $payment->payment_method_id;
            $invoice->save();
            $facturasProcesadas[] = $invoice;
        }

        return [
            'facturas_procesadas' => $facturasProcesadas,
            'credito_restante' => $rest,
            'mensaje' => 'Deuda viva saldada por FIFO (periodo más antiguo primero).',
        ];
    }

    private function crearNuevaFacturaProforma(Client $client, $period = null): Invoice
    {
        $client->refresh();
        $montos = $this->montosDelCliente($client);
        $period = $period ? $period : now()->format('Y-m');

        // Item #571: nace debiendo su total, como la del cron. Antes nacía con saldo 0 (quedaba
        // pagada sin consumir dinero) y vencía en la fecha de corte del día del cobro.
        $data = [
            'number' => $this->generateInvoiceNumber(),
            'client_id' => $client->id,
            'due_date' => $this->vencimientoDelPeriodo($client, $period),
            'subtotal' => $montos['subtotal'],
            'tax' => $montos['tax'],
            'total' => $montos['total'],
            'pending_balance' => $montos['total'],
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_PROFORMA,
            'notes' => "Factura creada automáticamente por pago anticipado",
            'created_by' => auth()->user()->id ?? 0,
            'period' => $period
        ];

        return $this->createProformaInvoice($data);
    }

    /**
     * Item #571: vencimiento de la proforma de un periodo = la fecha de corte del cliente dentro
     * de ese mes, la misma regla del cron (periodo = mes de fecha_corte, vence en fecha_corte).
     * Si el periodo es el del corte vigente, es esa fecha; si es otro mes, el mismo día en ese
     * mes (recortado a su último día). Sin corte válido: fin de mes del periodo. Periodo que no
     * es AAAA-MM (capturas viejas): la fecha de corte, como antes.
     */
    public function vencimientoDelPeriodo($client, string $period): ?string
    {
        $corte = ProformaAnticipada::corte($client->fecha_corte ?? null);
        if (! preg_match('/^(\d{4})-(\d{2})$/', trim($period), $m) || ! checkdate((int) $m[2], 1, (int) $m[1])) {
            return $corte ? $corte->toDateString() : null;
        }

        $mes = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfDay();
        if (! $corte) {
            return $mes->endOfMonth()->toDateString();
        }

        return $mes->day(min($corte->day, $mes->daysInMonth))->toDateString();
    }

    /** Lo que cuesta un periodo del cliente (sus servicios). Aparte para que las pruebas lo fijen. */
    protected function montosDelCliente(Client $client): array
    {
        return (new ClientRepository())->calculateAmounts($client);
    }

    /** Primera proforma abierta (no pagada ni cancelada) del periodo, la más vieja. */
    private function facturaAbiertaDelPeriodo($client, string $period): ?Invoice
    {
        return Invoice::proforma()
            ->where('client_id', $client->id)
            ->where('period', $period)
            ->notPaid()
            ->orderBy('id')
            ->first();
    }

    /**
     * Item #571: el periodo pedido o, si ya está pagado (o ya lo usó este mismo pago), el
     * siguiente mes que no lo esté. Un periodo que no es AAAA-MM se respeta tal cual.
     */
    private function periodoSinPagarDesde($client, string $period, array $usados): string
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $period;
        }

        $mes = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfDay();
        for ($i = 0; $i < 120; $i++) {
            $candidato = $mes->format('Y-m');
            if (! in_array($candidato, $usados, true) && empty($this->periodosYaPagados($client, [$candidato]))) {
                return $candidato;
            }
            $mes->addMonthNoOverflow();
        }

        return $period;
    }

    private function normalizarPeriodos($periods): array
    {
        $periods = is_array($periods) ? $periods : explode(',', (string) $periods);

        return collect($periods)->map(fn ($p) => trim((string) $p))->filter()->unique()->values()->all();
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
