<?php

namespace App\Http\Controllers\Module\Setting\TypeSeller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SellerType;

class TypeSellerController extends Controller
{
    public function index()
    {
        return view('meganet.module.setting.seller-type.index');
    }

    public function getAll()
    {
        $types = SellerType::all();
        return response()->json($types);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required'
        ]);

        $type = new SellerType();
        $type->name = $request->name;
        $type->save();

        return response()->json(['status' => 200, 'message' => 'Tipo de vendedor creado con éxito!']);
    }

    public function edit($id)
    {
        $type = SellerType::find($id);
        return response()->json($type);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required'
        ]);

        $type = SellerType::find($id);
        $type->name = $request->name;
        $type->save();

        return response()->json(['status' => 200, 'message' => 'Tipo de vendedor actualizado con éxito!']);
    }

    public function destroy($id)
    {
        $type = SellerType::find($id);
        $type->delete();

        return response()->json(['status' => 200, 'message' => 'Tipo de vendedor eliminado con éxito!']);
    }
}
