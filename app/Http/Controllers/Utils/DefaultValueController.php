<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

const FECHA = [
    'now' => 'getNowDefaultValue',
    'now-show' => 'getNowDefaultValueShow',
];

class DefaultValueController extends Controller
{
    public function getDefaultValue($fecha)
    {
        if (isset(FECHA[$fecha])) {
            $function = FECHA[$fecha];
            return $this->$function();
        }
        return null;
    }

    public function getNowDefaultValue()
    {
        return \Carbon\Carbon::now()->format('Y-m-d\TH:i');
    }

    public function getNowDefaultValueShow()
    {
        return \Carbon\Carbon::now()->format('Y-m-d H:i');
    }

    public function getDefaultValueForUserClient(Request $request)
    {
        $from = $request->session()->all()["_previous"]["url"];
        $clientId = Str::afterLast($from, '/');
        $res = ClientMainInformation::where('client_id', $clientId)->first();
        return $res->user;
    }

    public function getDefaultBillingDateForClient(Request $request)
    {
        $from = $request->session()->all()["_previous"]["url"];
        $clientId = Str::afterLast($from, '/');
        $client = Client::find($clientId);
        return $client ?
            Carbon::parse($client->create_at)->day :
            null;
    }
}
