<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Carbon\Carbon;

class ReceiptController extends Controller
{
    public function getReceiptForClient()
    {
        $today = Carbon::now()->toDateString();
        $countReceipt = Receipt::client()->count() + 1;
        return $today.$countReceipt;
    }

    public static function getStaticReceiptForClient(){
        $today = Carbon::now()->toDateString();
        $countReceipt = Receipt::client()->count() + 1;
        return $today.$countReceipt;
    }
}
