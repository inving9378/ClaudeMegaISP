<?php

namespace App\Http\Controllers\Module\Sellers\Cuts;

use App\Http\Controllers\Controller;
use App\Repositories\Sellers\Cuts\SuppliersExpenseRepository;
use Illuminate\Http\Request;

class SuppliersExpensesController extends Controller
{
    protected $repository;

    public function __construct()
    {
        $this->repository = new SuppliersExpenseRepository();
    }

    public function index($id)
    {
        $object = $this->repository->getByColumns(['box_id' => $id]);
        return response()->json($object);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['payment_date'] = substr($request->payment_date, 0, 10);
        $object = $this->repository->create($data);
        return response()->json($object);
    }

    public function update(Request $request, $id)
    {
        $object = $this->repository->find($id);
        $object = $this->repository->update($object, $request->all());
        return response()->json($object);
    }

    public function destroy($id)
    {
        $object = $this->repository->find($id);
        $object->delete();
        return response()->json($object);
    }
}
