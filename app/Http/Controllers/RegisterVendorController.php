<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;



class RegisterVendorController extends Controller
{

    public function index()
    {
        return view('meganet.module.vendors.register');
    }
}
