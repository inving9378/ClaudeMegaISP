<?php

namespace App\Http\Controllers\Module\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function getAll($id, $start_date, $end_date)
{
    $transactions = Transaction::join('client_main_information', 'transactions.client_id', '=', 'client_main_information.client_id')
        ->where('client_main_information.seller_id', $id)
        ->whereBetween('transactions.created_at', [$start_date, $end_date])
        ->get();

    return response()->json($transactions);
}

    public function getTotalTransactionByType($id, $start_date, $end_date)
    {
        $transactions = Transaction::join('client_main_information', 'transactions.client_id', '=', 'client_main_information.client_id')
            ->where('client_main_information.seller_id', $id)
            ->whereBetween('transactions.created_at', [$start_date, $end_date])
            ->get();

        $debitTotal = $transactions->where('type', 'debit')->count();
        $creditTotal = $transactions->where('type', 'credit')->count();

        return response()->json([
            [
                "type" => "Debito",
                "total" => $debitTotal
            ],
            [
                "type" => "Credito",
                "total" => $creditTotal
            ]
        ]);
    }
}
