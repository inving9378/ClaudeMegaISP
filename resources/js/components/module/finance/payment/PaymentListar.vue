<template>
    <div class="d-flex flex-wrap gap-2 mb-2 justify-content-end">
        <InputVuePickerMultiple
            :property="{
                field: 'date',
                label: 'Fecha',
                class_field: 'col-sm-12 col-md-9',
                class_label: 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                placeholder: 'Fecha',
            }"
            @update-field="setFilterPaymentMethodId"
            :modelValue="date"
            :errors="dataForm.data.errors"
            @change="clearError('date')"
        >
        </InputVuePickerMultiple>

        <SelectComponentWithCheckbox
            :property="{
                field: 'payment_method_id',
                label: 'Tipo de Pago',
                class_col: '',
                search: {
                    model: 'App\\Models\\MethodOfPayment',
                    id: `id`,
                    text: 'type',
                },
            }"
            @change="clearError('payment_method_id')"
            :modelValue="[]"
            :errors="dataForm.data.errors"
            @update-field="setFilterPaymentMethodId"
        />

        <div>
            <a
                href="javascript:void(0)"
                class="btn btn-outline-info waves-effect waves-light"
            >
                <i class="fas fa-history"></i>
            </a>
        </div>
    </div>
    <Datatable
        module="finanzas/pagos"
        model="FinancePayment"
        list="Listado Pagos"
    ></Datatable>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref, watch } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import VueDatePicker from "@vuepic/vue-datepicker";
import SelectComponentWithCheckbox from "../../../../shared/SelectComponentWithCheckbox";
import Form from "../../../../helpers/Form";

import { filters, resetTable } from "../../../../helpers/filters";
import InputVuePickerMultiple from "../../../../shared/InputVuePickerMultiple.vue";

export default {
    name: "PaymentListar",
    components: {
        Datatable,
        VueDatePicker,
        SelectComponentWithCheckbox,
        InputVuePickerMultiple,
    },
    props: {},
    setup(props) {
        const title = ref("Crear Proyecto");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/scheduling/project/add");
        const reloadCrud = ref(true);
        const date = ref("");

        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {});

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const setFilterPaymentMethodId = (obj) => {
            filters.value = {
                ...filters.value,
                [obj.field]: obj.value._value, // Asigna dinámicamente el valor al campo especificado
            };
        };

        return {
            title,
            action,
            table,
            reload,
            reloadCrud,
            date,
            dataForm,
            setFilterPaymentMethodId,
        };
    },
};
</script>

<style scoped></style>
