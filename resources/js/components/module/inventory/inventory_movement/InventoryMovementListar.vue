<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <SelectComponentWithCheckbox
            :property="{
                field: 'inventory_item_id',
                label: 'Artículo',
                class_col: '',
                search: {
                    model: 'App\\Models\\InventoryItem',
                    id: `id`,
                    text: 'name',
                },
                module_id: module_id,
            }"
            @change="clearError('inventory_item_id')"
            :modelValue="[]"
            :errors="dataForm.data.errors"
            @update-field="setFilter"
        />
        <SelectComponentWithCheckbox
            :property="{
                field: 'type',
                label: 'Tipo de Movimiento',
                class_col: '',
                options: {
                    Entrada: 'Entrada',
                    Salida: 'Salida',
                },
                module_id: module_id,
            }"
            @change="clearError('type')"
            :modelValue="[]"
            :errors="dataForm.data.errors"
            @update-field="setFilter"
        />
        <div>
            <InputVuePickerMultiple
                :property="{
                    field: 'created_at',
                    label: 'Fecha Creado',
                    class_field: 'col-sm-12 col-md-9',
                    class_label:
                        'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                    placeholder: 'Fecha Creado',
                }"
                @update-field="setFilter"
                :modelValue="created_at"
                :errors="dataForm.data.errors"
                @change="clearError('created_at')"
            >
            </InputVuePickerMultiple>
        </div>

        <div>
            <button
                type="button"
                class="btn btn-outline-primary waves-effect waves-light me-3"
                @click="reload"
            >
                Refrescar
            </button>
        </div>
    </div>
    <Datatable
        module="inventory/inventory_movement"
        model="InventoryMovement"
        list="Listado de Movimientos"
        @table="table"
        :editButton="{ modal: 'crudinventorymovement' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudinventorymovement"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <InventoryMovementCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></InventoryMovementCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import InventoryMovementCrud from "./InventoryMovementCrud";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import SelectComponentWithCheckbox from "../../../../shared/SelectComponentWithCheckbox.vue";
import { filters } from "../../../../helpers/filters";
import Form from "../../../../helpers/Form";
import InputVuePickerMultiple from "../../../../shared/InputVuePickerMultiple.vue";

export default {
    name: "InventoryMovementListar",
    components: {
        Datatable,
        InventoryMovementCrud,
        SelectComponentWithCheckbox,
        InputVuePickerMultiple,
    },
    props: {
        module_id: {
            type: String,
            default: null,
        },
    },
    setup(props) {
        const title = ref("Crear Movimiento");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/inventory/inventory_movement/add");
        const reloadCrud = ref(true);
        const dataForm = reactive({
            data: new Form({}),
        });
        const created_at = ref("");

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudinventorymovement").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Movimiento";
            action.value = "/inventory/inventory_movement/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudinventorymovement").modal("show");
            title.value = "Editar Movimiento";
            action.value = `/inventory/inventory_movement/update/${idItem}`;
        };

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const setFilter = (obj) => {
            filters.value = {
                ...filters.value,
                [obj.field]: obj.value._value, // Asigna dinámicamente el valor al campo especificado
            };
        };

        return {
            title,
            action,
            closeModal,
            table,
            reload,
            reloadCrud,
            setFilter,
            dataForm,
            created_at,
        };
    },
};
</script>

<style scoped></style>
