<?php

namespace App\Http\Controllers\Module\Setting\StatusSeller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SellerStatus;

class StatusSellerController extends Controller
{
    public function index()
    {
        return view('meganet.module.setting.seller-status.index');
    }

    public function getAll()
    {
        $statuses = SellerStatus::all();
        return response()->json($statuses);
    }

    public function store(Request $request)
    {
        $this->validate(request(), [
            'name' => 'required'
        ]);

        $status = new SellerStatus();
        $status->name = $request->name;
        $status->save();

        return response()->json(['status' => 200, 'message' => 'Estado de vendedor creado con éxito!']);
    }

    public function edit($id)
    {
        $status = SellerStatus::find($id);
        return response()->json($status);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required'
        ]);

        $status = SellerStatus::find($id);
        $status->name = $request->name;
        $status->save();

        return response()->json(['status' => 200, 'message' => 'Estado de vendedor actualizado con éxito!']);
    }

    public function destroy($id)
    {
        $status = SellerStatus::find($id);
        $status->delete();

        return response()->json(['status' => 200, 'message' => 'Estado del vendedor eliminado con éxito!']);
    }
}
