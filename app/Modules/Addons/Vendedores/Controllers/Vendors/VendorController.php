<?php

namespace App\Modules\Addons\Vendedores\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        return view('meganet.module.vendors.dashboard');
    }
}
