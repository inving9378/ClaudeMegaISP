<?php

namespace App\Http\Controllers\Module\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        return view('meganet.module.vendors.dashboard');
    }
}
