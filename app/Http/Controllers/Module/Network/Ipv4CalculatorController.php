<?php

namespace App\Http\Controllers\Module\Network;


use App\Http\HelpersModule\module\network\NetworkDatatableHelper;
use App\Http\Requests\module\network\Ipv4CalculatorRequest;
use App\Models\Network;
use App\Models\NetworkIp;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use IPv4\SubnetCalculator;

class Ipv4CalculatorController extends Controller
{

    public function calculator(Ipv4CalculatorRequest $request)
    {
        $sub = new SubnetCalculator($request->network_calculator, $request->bm_calculator);
        return $sub->getSubnetJSONReport();
    }

    public function createIpInNetwork($network_calculator,$bm_calculator)
    {
        $ipList = [];
        $sub = new SubnetCalculator($network_calculator, $bm_calculator);
        foreach ($sub->getAllHostIPAddresses() as $host_address) {
          array_push($ipList,$host_address);
        }
      return $ipList;
    }

    public function getRangesIP($network,$bm){
        $sub = new SubnetCalculator($network, $bm);
        return  $sub->getAddressableHostRange();
    }

}
