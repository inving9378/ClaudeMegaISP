<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\BoxInput;
use App\Repositories\BoxInputRepository;
use App\Repositories\TableRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoxInputController extends Controller
{
    protected $BoxInputRepository;

    public function __construct()
    {
        $this->BoxInputRepository = new BoxInputRepository();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->BoxInputRepository->getByBoxForDatatable($request->id);
    }

    public function list(Request $request)
    {
        return $this->BoxInputRepository->list($request->text, $request->box_id, $request->page);
    }

    public function listForFusion(Request $request)
    {
        return $this->BoxInputRepository->listForFusion(
            $request->text,
            $request->page,
            $request->object["id"]
        );
    }

    public function listForSplitterIn(Request $request)
    {
        return $this->BoxInputRepository->listForSplitterIn(
            $request->text,
            $request->page,
            $request->object["id"]
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try{
            DB::beginTransaction();

            $this->BoxInputRepository->create($request->all());

            DB::commit();

            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
                'res' => false,
                'message' => 'Ha ocurrido un error',
            ], 490);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function search(Request $request)
    {
        $object = $request->object;

        $data = $this->BoxInputRepository->SearchForSelect(
            $request->text,
            [$request->id],
            $request->page,
            function ($query) use($object){
                $query->leftJoin('equipment_links', function($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('equipment_links.input_id', 'box_inputs.id')
                                ->where('equipment_links.input_type', BoxInput::class);
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('equipment_links.output_id', 'box_inputs.id')
                                ->where('equipment_links.output_type', BoxInput::class);
                        });
                    })
                    ->where('box_id', '=', $object["id"])
                    ->whereNull('equipment_links.id');
            }
        );

        return response()->json($data, 200);
    }
}
