<?php

namespace App\Console\Commands\Scripts;

use App\Models\Payment;
use App\Models\GeneralAccountingIncome;
use App\Services\Finance\GeneralAccounting\GeneralAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeneraGeneralAccountingIncomeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:genera-general-accounting-income-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera ingresos contables a partir de pagos del año actual hasta hoy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info("Iniciando proceso de generación de ingresos contables...");

            DB::table('general_accounting_incomes')->truncate();
            $this->info("Tabla general_accounting_incomes truncada.");

            DB::beginTransaction();

            // Primer día del año actual hasta hoy (incluyendo tiempo para cubrir todo el día de hoy)
            $primerDiaDelAnioActual = date('Y-01-01 00:00:00');
            $hoy = date('Y-m-d 23:59:59');

            $this->info("Buscando pagos desde: {$primerDiaDelAnioActual} hasta: {$hoy}");

            // Eager load para evitar N+1
            $payments = Payment::with(['transactions' => function ($q) {
                $q->whereIn('category', [
                    'Pago',
                    'Pago de Costo de Activación',
                    'Pago de Costo de Instalación'
                ]);
            }])->whereBetween('created_at', [$primerDiaDelAnioActual, $hoy])->get();

            $this->info("Encontrados " . $payments->count() . " pagos.");

            $categories = [
                'Pago',
                'Pago de Costo de Activación',
                'Pago de Costo de Instalación'
            ];

            $generalAccountingService = new GeneralAccountingService();
            $array = [];
            $contador = 0;

            foreach ($payments as $payment) {
                $contador++;
                $this->info("Procesando pago {$contador}/{$payments->count()}: ID {$payment->id}");

                $transaction = $payment->transactions->first();

                // Validar que el pago tenga amount
                if (is_null($payment->amount)) {
                    $this->warn("Pago ID {$payment->id} tiene amount nulo, se usará 0");
                }

                $category = $transaction->category ?? 'Pago';
                if ($transaction && ($transaction->service_name == 'Costo de Activación' || $transaction->service_name == 'Costo de Instalación')) {
                    $category = $transaction->description;
                }


                $array[] = [
                    'payment_id'       => $payment->id,
                    'client_id'        => $payment->paymentable_id ?? null,
                    'created_by'       => 0,
                    'reference_number' => $generalAccountingService->generateReferenceNumber('INC'),
                    'description'      => $transaction->description ?? 'Pago autogenerado - ID: ' . $payment->id,
                    'transaction_id'   => $transaction->id ?? null,
                    'amount'           => $payment->amount ?? 0,
                    'category'         => $category,
                    'created_at'       => $payment->created_at ?? now(), // Usar fecha del pago original
                    'updated_at'       => now(),
                ];
            }

            $this->info("Preparados " . count($array) . " registros para insertar.");

            if (!empty($array)) {
                foreach ($array as $key => $value) {
                    DB::table('general_accounting_incomes')->insert($value);
                }

                $this->info(count($array) . " ingresos insertados correctamente.");
            } else {
                $this->info("No hay registros para insertar.");
            }

            DB::commit();
            $this->info("Proceso completado exitosamente.");

        } catch (\Throwable $th) {
            DB::rollBack();
            $this->error("ERROR: " . $th->getMessage());
            $this->error("Línea: " . $th->getLine());
            $this->error("Archivo: " . $th->getFile());

            // Debug adicional
            if (method_exists($th, 'getPrevious') && $th->getPrevious()) {
                $this->error("Previous: " . $th->getPrevious()->getMessage());
            }
        }
    }
}
