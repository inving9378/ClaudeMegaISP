<?php

namespace App\Http\Controllers\Module\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function getPayments($id)
    {
        $clients = DB::table('client_main_information')
        ->where('seller_id', $id)
        ->get();

        $client_payments = [];

        foreach ($clients as $client) {
            $activation_date = date('Y-m-d', strtotime($client->activation_date));

            $payments_count = DB::table('payments')
            ->join('transactions', 'payments.id', '=', 'transactions.payment_id')
            ->where('transactions.client_id', $client->client_id)
            ->whereDate('transactions.date', '>=', $activation_date)
            ->count();

            $full_name = $client->name . ' ' . $client->father_last_name . ' ' . $client->mother_last_name;
        
        }
            $client_payments[] = [
                'id' => $client->client_id,
                'full_name' => $full_name,
                'payment_1' => $payments_count >= 1,
                'payment_2' => $payments_count >= 2,
                'payment_3' => $payments_count >= 3,
                'payment_4' => $payments_count >= 4,
                'payment_5' => $payments_count >= 5,
                'date_activation' => $client->activation_date,
            ];
        

        return response()->json($client_payments);
    }

}
