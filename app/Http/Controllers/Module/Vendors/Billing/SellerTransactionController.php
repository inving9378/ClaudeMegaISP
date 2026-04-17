<?php

namespace App\Http\Controllers\Module\Vendors\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\TransactionSeller;

class SellerTransactionController extends Controller
{
    public function getTransactionsList(Request $request, $seller_id, $start_date = null, $end_date = null, $method_of_payment = null)
    {
        $search = $request->input('search', '');

        $transactions = DB::table('transactions_sellers')
            ->join('method_of_payments', 'transactions_sellers.method_of_payment', '=', 'method_of_payments.id')
            ->where('transactions_sellers.seller_id', $seller_id)
            ->where(function($query) use ($search) {
                $query->where('transactions_sellers.transaction_date', 'like', "%{$search}%")
                    ->orWhere('method_of_payments.type', 'like', "%{$search}%")
                    ->orWhere('transactions_sellers.previous_balance', 'like', "%{$search}%")
                    ->orWhere('transactions_sellers.new_balance', 'like', "%{$search}%");
            })
            ->when($start_date, function ($query) use ($start_date) {
                return $query->whereDate('transactions_sellers.transaction_date', '>=', $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                return $query->whereDate('transactions_sellers.transaction_date', '<=', $end_date);
            })
            ->when($method_of_payment && $method_of_payment !== 'all', function ($query) use ($method_of_payment) {
                return $query->where('transactions_sellers.method_of_payment', $method_of_payment);
            })
            ->orderBy('transactions_sellers.transaction_date', 'desc')
            ->select(
                'transactions_sellers.*',
                'method_of_payments.type as method_of_payment_name'
            )
            ->paginate(49);

        return response()->json($transactions);
    }

}
