<?php

namespace App\Http\Controllers\Module\Setting\MediumSale;

use App\Http\Controllers\Controller;
use App\Models\MediumOfSale;
use Illuminate\Http\Request;

class MediumSaleController extends Controller
{
    public function index()
    {
        return view('meganet.module.setting.medium-sales.index');
    }

    public function getAll()
    {
        $medium_sales = MediumOfSale::all();
        return response()->json($medium_sales);
    }

    public function getById($id)
    {
        $medium_sale = MediumOfSale::find($id);
        return response()->json($medium_sale);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|unique:medium_sales',
        ]);

        $medium_sale = new MediumOfSale();
        $medium_sale->name = $request->name;

        $medium_sale->save();

        return response()->json(['status' => 200, 'message' => 'Medio de venta creado con éxito!']);
    }
    
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|string',
        ]);

        $medium_sale = MediumOfSale::find($id);
        $medium_sale->name = $request->name;

        $medium_sale->save();
        
        return response()->json(['status' => 200, 'message' => 'Medio de venta actualizado con éxito!']);
    }

    public function destroy($id)
    {
        $medium_sale = MediumOfSale::find($id);
        $medium_sale->delete();
        
        return response()->json(['status' => 200, 'message' => 'Medio de venta eliminado con éxito!']);
    }
}
