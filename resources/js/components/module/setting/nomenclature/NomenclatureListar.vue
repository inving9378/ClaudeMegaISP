<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light ms-auto"
            @click="reload"
        >
            Refrescar
        </button>
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light"
            data-bs-toggle="modal"
            data-bs-target="#crudnomenclatureMultiple"
        >
            Agregar en Masa
        </button>
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light"
            data-bs-toggle="modal"
            data-bs-target="#crudnomenclature"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="configuracion/nomenclature"
        model="Nomenclature"
        list="Listado de Nomenclatura"
        @table="table"
        :editButton="{ modal: 'crudnomenclature' }"
        :select_filter="filters"
    ></Datatable>

    <div
        class="modal fade"
        id="crudnomenclature"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <NomenclatureCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></NomenclatureCrud>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="crudnomenclatureMultiple"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Agregar Nomenclatura</h6>
                </div>

                <div class="modal-body m-0 row">
                    <div :class="`col-12 row mb-2`">
                        <label
                            for="nameDistrict"
                            class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                        >
                            Nombre Distrito
                        </label>
                        <div :class="`col-sm-12 col-md-9`">
                            <input
                                type="string"
                                id="nameDistrict"
                                name="nameDistrict"
                                placeholder="D"
                                class="form-control"
                                v-model="nameDistrict"
                                :disabled="disabledForm"
                            />
                        </div>
                    </div>
                    <div :class="`col-12 row mb-2`">
                        <label
                            for="districts"
                            class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                        >
                            Cantidad de Distritos
                        </label>
                        <div :class="`col-sm-12 col-md-9`">
                            <input
                                type="number"
                                id="districts"
                                name="districts"
                                placeholder="1"
                                class="form-control"
                                v-model="districts"
                                :disabled="disabledForm"
                            />
                        </div>
                    </div>

                    <div :class="`col-12 row mb-2`">
                        <label
                            for="nameZone"
                            class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                        >
                            Nombre Zona
                        </label>
                        <div :class="`col-sm-12 col-md-9`">
                            <input
                                type="string"
                                id="nameZone"
                                name="nameZone"
                                placeholder="Z"
                                class="form-control"
                                v-model="nameZone"
                                :disabled="disabledForm"
                            />
                        </div>
                    </div>

                    <div :class="`col-12 row mb-2`">
                        <label
                            for="zones"
                            class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                        >
                            Cantidad de Zonas por distritos
                        </label>
                        <div :class="`col-sm-12 col-md-9`">
                            <input
                                type="number"
                                id="zones"
                                name="zones"
                                placeholder="100"
                                class="form-control"
                                v-model="zones"
                                :disabled="disabledForm"
                            />
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        class="btn btn-primary"
                        type="button"
                        @click="closeMultipleForm"
                    >
                        Cerrar
                    </button>

                    <button
                        class="btn btn-primary"
                        type="button"
                        @click="submitForm"
                    >
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <ChangeClient :action="action" @resetTable="reload"></ChangeClient>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import NomenclatureCrud from "./NomenclatureCrud.vue";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import ChangeClient from "./ChangeClient.vue";

export default {
    name: "NomenclatureListar",
    components: { Datatable, NomenclatureCrud, ChangeClient },
    props: {
        filters: String,
    },
    setup(props) {
        const title = ref("Crear Nomenclatura");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/configuracion/nomenclature/add");
        const reloadCrud = ref(true);

        const districts = ref(null);
        const zones = ref(null);
        const disabledForm = ref(false);
        const nameDistrict = ref("");
        const nameZone = ref("");

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });

            $(document).on("click", "#change_client_nomenclature", function (e) {
                let idItem =$(e.target).parent().attr("id-item");
                showModalChangeClient(idItem);
            });
        });

        const closeModal = () => {
            $("#crudnomenclature").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Nomenclatura";
            action.value = "/configuracion/nomenclature/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudnomenclature").modal("show");
            title.value = "Editar Nomenclatura";
            action.value = `/configuracion/nomenclature/update/${idItem}`;
        };

        const showModalChangeClient = (idItem) => {
            action.value = `/configuracion/nomenclature/update/${idItem}`;
            $("#modaleChangeClientNomenclature").modal("show");
        };

        const reload = () => {
            reloadCrud.value = !reloadCrud.value;
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        // Función para manejar el envío del formulario
        const submitForm = async () => {
            const formData = new FormData();
            formData.append("dist", districts.value);
            formData.append("zon", zones.value);
            formData.append("nameDist", nameDistrict.value);
            formData.append("nameZon", nameZone.value);
            showLoading("showTextDef");
            disabledForm.value = true;
            try {
                // Aquí utilizamos axios para enviar la solicitud POST
                await axios
                    .post(
                        "/configuracion/nomenclature/add-multiple-nomenclatures",
                        formData,
                        {
                            headers: {
                                "Content-Type": "multipart/form-data", // Esto es necesario para enviar archivos
                            },
                        }
                    )
                    .then((response) => {
                        
                    });
                hideLoading();
                toastr.success(
                    `Documento subido Satisfactoriamente`,
                    "Nomenclature"
                );
                closeMultipleForm();
            } catch (error) {
                alert("Hubo un error al importar las nomenclaturas");
                console.error("Error en el envío:", error);
                hideLoading();
                disabledForm.value = false;
            }
        };

        const closeMultipleForm = () => {
            districts.value = null;
            zones.value = null;
            nameDistrict.value = "";
            nameZone.value = "";
            disabledForm.value = false;
            $("#crudnomenclatureMultiple").modal("hide");
        };

        return {
            title,
            action,
            closeModal,
            table,
            reload,
            reloadCrud,
            submitForm,
            zones,
            districts,
            disabledForm,
            closeMultipleForm,
            nameDistrict,
            nameZone,
        };
    },
};
</script>

<style scoped></style>
