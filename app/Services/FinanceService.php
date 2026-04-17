<?php

namespace App\Services;

use App\Http\Repository\ClientInvoiceRepository;
use App\Http\Repository\PaymentRepository;

class FinanceService
{
    public function getInfoFinanceDashboard()
    {
        $currentMonth = $this->getInfoPaymentCurrentMonth();
        $lastMonth = $this->getInfoPaymentLastMonth();
        return array_merge($currentMonth, $lastMonth);
    }


    public function getInfoPaymentCurrentMonth()
    {
        $paymentRepository = new PaymentRepository();
        $clientInvoiceRepository = new ClientInvoiceRepository();
        return [
            'totalPaymentCurrentMonth' => $paymentRepository->getTotalPaymentCurrentMonth(),
            'totalAmountPaymentCurrentMonth' => $paymentRepository->getTotalAmountPaymentCurrentMonth(),
            'totalInvoiceCurrentMonth' => $clientInvoiceRepository->getTotalInvoiceCurrentMonth(),
            'totalAmountInvoiceCurrentMonth' => $clientInvoiceRepository->getTotalAmountInvoiceCurrentMonth(),
            'totalInvoicePendingCurrentMonth' => $clientInvoiceRepository->getTotalInvoicePendingCurrentMonth(),
            'totalAmountInvoicePendingCurrentMonth' => $clientInvoiceRepository->getTotalAmountInvoicePendingCurrentMonth()
        ];
    }

    public function getInfoPaymentLastMonth()
    {
        $paymentRepository = new PaymentRepository();
        $clientInvoiceRepository = new ClientInvoiceRepository();
        return [
            'totalPaymentLastMonth' => $paymentRepository->getTotalPaymentLastMonth(),
            'totalAmountPaymentLastMonth' => $paymentRepository->getTotalAmountPaymentLastMonth(),
            'totalInvoiceLastMonth' => $clientInvoiceRepository->getTotalInvoiceLastMonth(),
            'totalAmountInvoiceLastMonth' => $clientInvoiceRepository->getTotalAmountInvoiceLastMonth(),
            'totalInvoicePendingLastMonth' => $clientInvoiceRepository->getTotalInvoicePendingLastMonth(),
            'totalAmountInvoicePendingLastMonth' => $clientInvoiceRepository->getTotalAmountInvoicePendingLastMonth()
        ];
    }
}
