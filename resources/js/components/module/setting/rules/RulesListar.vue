<template>
    <div align="right" class="q-gutter-sm q-pb-sm">
        <button
            type="button"
            class="btn btn-success waves-effect waves-light"
            @click="openForm"
        >
            Agregar
        </button>
        <button
            type="button"
            class="btn btn-success waves-effect waves-light"
            @click="loadConfig"
        >
            Configuración general
        </button>
    </div>
    <Datatable
        module="configuracion/rules"
        model="CommissionRule"
        list="Listado de Reglas"
        @table="table"
    ></Datatable>

    <q-dialog v-model="showDialog" persistent>
        <q-card style="width: 400px; max-width: 50vw">
            <q-card-section>
                <div class="text-h6">Configuración general</div>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll q-px-sm">
                <q-form ref="formRef" greedy class="q-px-md">
                    <div class="row">
                        <label for="object_iva">IVA</label>
                        <q-input
                            v-model.number="formData.iva"
                            for="object_iva"
                            outlined
                            type="number"
                            dense
                            :rules="[
                                (val) =>
                                    (val !== null && val !== '') || 'Requerido',
                                (val) =>
                                    val > 0 ||
                                    'Debe ser un valor mayor o igual a 0',
                            ]"
                            style="margin-left: 0px"
                        />
                    </div>

                    <div class="row">
                        <label for="object_installation_cost"
                            >Costo de instalación</label
                        >
                        <q-input
                            v-model.number="formData.installation_cost"
                            for="object_installation_cost"
                            outlined
                            type="number"
                            dense
                            :rules="[
                                (val) =>
                                    (val !== null && val !== '') || 'Requerido',
                                (val) =>
                                    val > 0 ||
                                    'Debe ser un valor mayor o igual a 0',
                            ]"
                            style="margin-left: 0px"
                        />
                    </div>
                </q-form>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Guardar"
                    no-caps
                    @click="saveConfig"
                    color="primary"
                />
                <q-btn
                    label="Cancelar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import Swal from "sweetalert2";
import axios from "axios";
import { showLoading, hideLoading } from "../../../../helpers/loading";

defineOptions({
    name: "RulesListar",
});
const title = ref("Crear Tipo de Articulo");
const datatable = reactive({
    table: new DatatableHelper({}),
});
const action = ref("/inventory/inventory_item_type/add");
const reloadCrud = ref(true);
const showDialog = ref(false);
const formRef = ref(null);
const formData = ref({
    id: 0,
    iva: 16,
    installation_cost: 1500,
});

onMounted(() => {
    $(document).on("click", ".uil-pen-modal", function () {
        let idItem = $(this).parent().attr("id-item");
        let modal = $(this).parent().attr("toggle-modal");
        showEditModal(idItem, modal);
    });
});

const closeModal = () => {
    $("#crudinventoryitemtype").modal("hide");
    reloadCrud.value = !reloadCrud.value;
    title.value = "Crear Tipo de Articulo";
    action.value = "/inventory/inventory_item_type/add";
    datatable.table.reload();
};

const showEditModal = (idItem) => {
    $("#crudinventoryitemtype").modal("show");
    title.value = "Editar Tipo de Articulo";
    action.value = `/inventory/inventory_item_type/update/${idItem}`;
};

const reload = () => {
    datatable.table.reload();
};

const table = (refTable) => {
    datatable.table = new DatatableHelper(refTable);
};

const openForm = () => {
    window.location.href = "/configuracion/rules/create";
};

const loadConfig = async () => {
    showLoading("showTextDef");
    await axios
        .post("/configuracion/rules/general-config")
        .then((response) => {
            let { id, iva, installation_cost } = response.data;
            formData.value = { iva, installation_cost, id };
            showDialog.value = true;
        })
        .catch((r) => {
            Swal.fire(
                "¡Error!",
                "Hubo un error al tratar de obtener la configuración",
                "error"
            );
        })
        .finally((f) => {
            hideLoading();
        });
};

const saveConfig = () => {
    formRef.value.validate().then(async (success) => {
        if (success) {
            await axios
                .post(
                    "/configuracion/rules/save-general-config",
                    formData.value
                )
                .then((response) => {
                    showDialog.value = false;
                    showMsg("success", "Configuración guardada correctamente");
                })
                .catch((r) => {
                    Swal.fire(
                        "¡Error!",
                        "Hubo un error al tratar de guardar la configuración",
                        "error"
                    );
                })
                .finally((f) => {
                    hideLoading();
                });
        } else {
            showMsg("error", "Rectifique los errores");
        }
    });
};

const showMsg = (type, msg) => {
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        },
    });
    Toast.fire({
        icon: type,
        text: msg,
    });
};
</script>

<style scoped></style>
