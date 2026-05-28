<?php

namespace App\Http\HelpersModule\module\inventory\supplierinvoice;

use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\SupplierInvoice;

class SupplierInvoiceDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(SupplierInvoice::class, 'SupplierInvoice');
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        $query = $this->model::with(['supplier', 'supplierVendor']);

        if ($filters) {
            return $query->filters($this->columns, null, $filters)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        }

        return $query->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        return $this->model::with(['supplier', 'supplierVendor'])
            ->filters($this->columns, $search, $filters)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search)
    {
        return $this->model::with(['supplier', 'supplierVendor'])
            ->filters($this->columns, $search)
            ->count();
    }

    public function transform($request)
    {
        $data = [];

        if (!empty($request['array'])) {
            foreach ($request['array'] as $value) {
                $id = $value->id;
                $nestedData = [];

                foreach ($this->columns as $col) {
                    $nestedData[$col] = match ($col) {
                        'status'             => $value->status_name,
                        'supplier_id'        => $value->supplier->name ?? '',
                        'supplier_vendor_id' => $value->supplierVendor->full_name ?? '—',
                        'total'              => '$' . number_format($value->total, 2),
                        default              => $value->$col ?? '',
                    };
                }

                if (!isset($nestedData['supplier_vendor_id'])) {
                    $nestedData['supplier_vendor_id'] = $value->supplierVendor->full_name ?? '—';
                }

                $invoiceLabel = $value->invoice_number ?? '#' . $id;
                $nestedData['invoice_number'] = view('meganet.shared.table.module.inventory.supplier_invoice.number', [
                    'dir'  => "/inventory/supplier-invoice/show/$id",
                    'name' => $invoiceLabel,
                ])->toHtml();

                $nestedData['action'] = view('meganet.shared.table.module.inventory.supplier_invoice.actions', [
                    'id'        => $id,
                    'module'    => 'SupplierInvoice',
                    'group'     => 'supplier',
                    'submodule' => 'supplier_invoice',
                    'status'    => $value->status,
                ])->toHtml();

                $data[] = $nestedData;
            }
        }

        return [
            'draw'            => intval($request['request']->input('draw')),
            'recordsTotal'    => intval($request['totalData']),
            'recordsFiltered' => intval($request['totalFiltered']),
            'data'            => $data,
        ];
    }
}
