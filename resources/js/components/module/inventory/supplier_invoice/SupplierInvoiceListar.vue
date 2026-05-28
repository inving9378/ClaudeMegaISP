<template>
    <div>
        <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-primary waves-effect waves-light" @click="reload">
                    Refrescar
                </button>
                <a href="/inventory/supplier-invoice/create" class="btn btn-outline-primary waves-effect waves-light">
                    <i class="fas fa-plus me-1"></i> Nueva Factura
                </a>
            </div>
        </div>

        <Datatable
            module="inventory/supplier-invoice"
            model="SupplierInvoice"
            list="Listado de Facturas de Proveedores"
            @table="table"
        ></Datatable>
    </div>
</template>

<script>
import { reactive } from "vue";
import Datatable from "../../../base/shared/Datatable.vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "SupplierInvoiceListar",
    components: { Datatable },
    setup() {
        const datatable = reactive({ table: new DatatableHelper({}) });

        const reload = () => datatable.table.reload();
        const table  = (refTable) => { datatable.table = new DatatableHelper(refTable); };

        return { reload, table };
    },
};
</script>
