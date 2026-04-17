<?php

namespace App\Services\Finance\GeneralAccounting;

use App\Models\GeneralAccountingExpense;
use App\Models\GeneralAccountingIncome;
use App\Models\GeneralAccountingOperation;

class GeneralAccountingService
{


    public function setNewGeneralAccountingIncomeByData($data)
    {
        $generalAccountingIncome = new GeneralAccountingIncome();
        return $generalAccountingIncome->create($data);
    }

    public function setNewGeneralAccountingExpenseByData($data)
    {
        $generalAccountingExpense = new GeneralAccountingExpense();
        return $generalAccountingExpense->create($data);
    }

    public function setNewGeneralAccountingIncomeBeforePayment($payment, $client, $transaction)
    {
        $generalAccountingIncome = new GeneralAccountingIncome();
        $generalAccountingIncome->create([
            'payment_id' => $payment->id,
            'client_id' => $client->id,
            'created_by' => auth()->user()->id,
            'reference_number' => $this->generateReferenceNumber(),
            'description' => $transaction->description,
            'transaction_id' => $transaction->id,
            'amount' => $payment->amount,
            'category' => 'Pago',
        ]);
    }

    public function setNewGeneralAccountingIncomeBeforeCostInstallationPayment($payment, $client, $transaction = null)
    {
        $generalAccountingIncome = new GeneralAccountingIncome();
        $generalAccountingIncome->create([
            'payment_id' => $payment->id,
            'client_id' => $client->id,
            'created_by' => auth()->user()->id,
            'reference_number' => $this->generateReferenceNumber(),
            'description' => 'Pago de Costo de Instalación',
            'amount' => $payment->amount,
            'transaction_id' => $transaction ? $transaction->id : null,
            'category' => 'Costo de Instalación',
        ]);
    }

    public function setNewGeneralAccountingIncomeBeforeActivationCostPayment($payment, $client, $transaction = null)
    {
        $generalAccountingIncome = new GeneralAccountingIncome();
        $generalAccountingIncome->create([
            'payment_id' => $payment->id,
            'client_id' => $client->id,
            'created_by' => auth()->user()->id,
            'reference_number' => $this->generateReferenceNumber(),
            'description' => 'Pago de Costo de Activación',
            'amount' => $payment->amount,
            'transaction_id' => $transaction ? $transaction->id : null,
            'category' => 'Costo de Activación',
        ]);
    }

    public function generateReferenceNumber($prefix = 'INC')
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
        return "{$prefix}-{$year}{$month}-" . str_pad($uniqueId, 12, '0', STR_PAD_LEFT);
    }



    public function setNewGeneralAccountingOperationByData($data)
    {
        $generalAccountingOperation = GeneralAccountingOperation::create($data);
        return $generalAccountingOperation;
    }

}
