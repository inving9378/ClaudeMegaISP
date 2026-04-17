<?php


namespace App\Http\HelpersModule\module\sellers\seller;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Seller;
use Illuminate\Support\Arr;

class SellerDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Seller::class, 'Seller');
    }

    public function transform($request)
    {
        $data = array();

        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'type') {
                        $value->type = $value->type->name ?? '';
                    }
                    if ($val == 'name') {
                        $value->name = $value->user->name ?? '';
                    }
                    if ($val == 'father_last_name') {
                        $value->father_last_name = $value->user->father_last_name ?? '';
                    }
                    if ($val == 'mother_last_name') {
                        $value->mother_last_name = $value->user->mother_last_name ?? '';
                    }
                    if ($val == 'phone') {
                        $value->phone = $value->user->phone ?? '';
                    }
                    if ($val == 'address') {
                        $value->address = $value->user->address ?? '';
                    }
                    if ($val == 'municipality') {
                        $value->municipality = $value->user->city_municipality ?? '';
                    }
                    if ($val == 'state') {
                        $value->state = $value->user->state_country ?? '';
                    }
                    if ($val == 'rfc') {
                        $value->rfc = $value->user->rfc ?? '';
                    }
                    if ($val == 'status') {
                        $value->status = $value->status->name ?? '';
                    }

                    $nestedData[$val] = $value->$val;
                }

                 $userId = $value->user->id;

                $info = [
                    'id' => $id,
                    'module' => strtolower($this->moduleName),
                    'group' => strtolower($this->moduleName),
                    'submodule' => strtolower($this->moduleName),
                    'user_id' => $userId
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.module.sellers.seller.actions', $info)->toHtml();
                $nestedData['type'] = view('meganet.shared.table.module.sellers.seller.type', [
                    'type' => $value->type,
                ])->toHtml();
                $nestedData['status'] = view('meganet.shared.table.module.sellers.seller.status', [
                    'status' => $value->status,
                ])->toHtml();

                $nestedData['name'] = view('meganet.shared.table.module.sellers.seller.name', [
                    'dir'=>"/vendedores/$id/seguimiento-vendedor/$userId",
                    'name' => $value->name,
                ])->toHtml();
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data
        ];
    }
}
