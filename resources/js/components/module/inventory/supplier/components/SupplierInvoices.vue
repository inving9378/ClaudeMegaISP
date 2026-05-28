<template>
    <div>
        <div class="d-flex justify-content-end mb-2">
            <a
                :href="`/inventory/supplier-invoice/create`"
                class="btn btn-outline-primary btn-sm"
            >
                <i class="fas fa-plus me-1"></i> Nueva Factura
            </a>
        </div>

        <Datatable
            module="inventory/supplier-invoice"
            model="SupplierInvoice"
            list="Listado de Facturas de Proveedores"
            :filters="{ supplier_id: [supplierId] }"
            @table="table"
        ></Datatable>
    </div>
</template>

<script>
import { reactive } from "vue";
import Datatable from "../../../../base/shared/Datatable.vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";

export default {
    name: "SupplierInvoices",
    components: { Datatable },
    props: {
        supplierId: {
            type: [Number, String],
            required: true,
        },
    },
    setup(props) {
        const datatable = reactive({ table: new DatatableHelper({}) });

        const table = (refTable) => { datatable.table = new DatatableHelper(refTable); };

        return { table, supplierId: props.supplierId};
    },
};
</script>