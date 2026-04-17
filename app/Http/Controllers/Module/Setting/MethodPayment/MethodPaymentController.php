<?php

namespace App\Http\Controllers\Module\Setting\MethodPayment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MethodOfPayment;

class MethodPaymentController extends Controller
{
    public function index()
    {
        return view('meganet.module.setting.method-payments.index');
    }

    public function getAll()
    {
        $method_payments = MethodOfPayment::all();
        return response()->json($method_payments);
    }

    public function edit($id)
    {
        $method_payment = MethodOfPayment::find($id);
        return response()->json($method_payment);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'type' => 'required|string|unique:method_of_payments',
        ]);

        $method_payment = new MethodOfPayment();
        $method_payment->type = $request->type;

        $method_payment->save();

        return response()->json(['status' => 200, 'message' => 'Método de pago creado']);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'type' => 'required|string',
        ]);

        $method_payment = MethodOfPayment::find($id);
        $method_payment->type = $request->type;
        $method_payment->active = $request->active;

        $method_payment->save();
        
        return response()->json(['status' => 200, 'message' => 'Método de pago actualizado']);
    }

    public function destroy($id)
    {
        $method_payment = MethodOfPayment::find($id);
        $method_payment->delete();
        
        return response()->json(['status' => 200, 'message' => 'Método de pago eliminado']);
    }
}
