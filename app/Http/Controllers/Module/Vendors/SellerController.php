<?php

namespace App\Http\Controllers\Module\Vendors;

use App\Models\User;
use App\Models\Seller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\ClientMainInformation;
use App\Models\CrmMainInformation;
use App\Models\CrmLeadInformation;
use App\Models\Credential;
use App\Models\MediumOfSale;
use App\Models\SellerType;
use App\Models\SellerStatus;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;
use PDF;

class SellerController extends Controller
{
    // ? ********************************************************
    // ? *************** CRUD DE LOS VENDEDORES *****************
    // ? ********************************************************

    public function showView()
    {
        return view('meganet.module.vendors.list');
    }

    public function index()
    {
        $sellers = Seller::whereHas('user', function ($query) {
            $query->whereHas('roles', function ($query) {
                $query->where('name', 'Vendedor');
            });
        })
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->join('seller_status', 'sellers.status_id', '=', 'seller_status.id')
            ->join('seller_types', 'sellers.type_id', '=', 'seller_types.id')
            ->select('sellers.id as seller_id', 'users.*', 'seller_status.name as status_seller', 'seller_types.name as type', 'sellers.balance')
            ->get();

        return response()->json($sellers);
    }

    public function edit($seller_id, $user_id)
    {
        $mediums_of_sales = MediumOfSale::select('name')->get();
        $user = User::find($user_id);
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('Seller');
        $this->data['seller_id'] = $seller_id;
        $this->data['user_id'] = $user_id;
        $this->data['sucursal_id'] = $user->sucursal_id;
        $this->data['is_counter'] = $user->isCounter();
        $this->data['mediums_of_sales'] = $mediums_of_sales;

        return view('meganet.module.vendors.menu', $this->data);
    }

    public function showPanel()
    {
        $user = auth()->user();
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('Seller');

        if ($user->hasRole('Vendedor')) {
            $seller = Seller::where('user_id', $user->id)->first();

            if ($seller) {
                $seller_id = $seller->id;
                $user_id = $user->id;
                $this->data['seller_id'] = $seller_id;
                $this->data['user_id'] = $user_id;
                $this->data['sucursal_id'] = $user->sucursal_id;
                $this->data['is_counter'] = $user->isCounter();
                return view('meganet.module.vendors.panel', $this->data);
            }
        }

        $error = 'No hay nada que mostrar, este usuario no tiene rol de vendedor';
        return view('meganet.module.vendors.panel', compact('error'));
    }

    public function getTypesSeller()
    {
        $types = SellerType::all();
        return response()->json($types);
    }

    public function getStatusSeller()
    {
        $status = SellerStatus::all();
        return response()->json($status);
    }

    public function getDataById($id)
    {
        $seller = Seller::join('users', 'sellers.user_id', '=', 'users.id')
            ->select('sellers.id as seller_id', 'sellers.type_id', 'sellers.status_id', 'users.*')
            ->find($id);

        return response()->json($seller);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'status_id' => 'required',
            'type_id' => 'required',
        ]);

        $seller = Seller::find($id);

        $seller->status_id = $request->status_id;
        $seller->type_id = $request->type_id;

        $seller->save();

        return response()->json(['status' => 200, 'message' => 'Vendedor actualizado correctamente']);
    }

    public function pdf($id)
    {
        $seller = Seller::join('users', 'sellers.user_id', '=', 'users.id')
            ->select('sellers.id as seller_id', 'sellers.type_id', 'sellers.status_id', 'users.*')
            ->find($id);

        $front_image_name = Credential::where('type', 'frontal')->select('name')->first();
        $back_image_name = Credential::where('type', 'reverso')->select('name')->first();
        $logo_image_name = Credential::where('type', 'logo')->select('name')->first();
        //$seller = Seller::find($id);
        $pdf = PDF::loadView('meganet.module.vendors.pdf', compact('seller', 'front_image_name', 'back_image_name', 'logo_image_name'));
        return $pdf->stream();
        //return $pdf->download('credencial.pdf');
    }

    public function show()
    {
        return view('livewire.vendors.menu-details');
    }
}
