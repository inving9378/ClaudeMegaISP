<template>
    <div
        class="modal fade"
        id="modalcategory"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <form
                method="POST"
                @submit.prevent="onSubmit"
                @change="dataForm.data.errors.clear($event.target.name)"
                @keydown="dataForm.data.errors.clear($event.target.name)"
            >
                <div class="modal-content" style="min-height: 500px">
                    <div class="modal-header">
                        <h6 class="m-auto">Agregar Clasificación</h6>
                    </div>
                    <div class="modal-body m-0">
                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>
                    </div>
                    <div class="modal-footer">
                        <a
                            class="btn btn-secondary me-3"
                            href="javascript:void(0)"
                            @click="closeModal"
                            >Cerrar</a
                        >

                        <button
                            class="btn btn-primary"
                            type="submit"
                            :disabled="dataForm.data.errors.any()"
                        >
                            Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch, computed, reactive } from "vue";
import ComponentFormDefault from "../../../../ComponentFormDefault.vue";
import { requestFieldsByModule } from "../../../../../helpers/Request";
import Form from "../../../../../helpers/Form";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import Swal from "sweetalert2";
export default {
    name: "AddCategory",
    props: {},
    components: {
        ComponentFormDefault,
    },
    emits: ["cleanModal"],
    setup(props, { emit }) {
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            await getfieldsJson();
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const getfieldsJson = async () => {
            fieldsJson.value = await requestFieldsByModule(
                "GeneralAccountingCategory"
            );
            dataForm.data = new Form(fieldsJson.value);
        };

        const onSubmit = () => {
            showLoading("showTextDef");
            dataForm.data
                .submit(
                    "post",
                    `/finanzas/general-accounting/category/add`,
                    "reset"
                )
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        hideLoading();
                        closeModal();
                    } else {
                        Swal.fire("Error", message, "error");
                        hideLoading();
                    }
                })
                .catch((error) => {
                    console.log(error);
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrió un error inesperado.",
                        "error"
                    );
                    hideLoading();
                })
                .finally(() => {
                    hideLoading();
                });
        };

        return {
            fieldsJson,
            dataForm,
            initComponent,
            closeModal,
            updateThisField,
            clearError,
            getfieldsJson,
            onSubmit,
        };
    },
};
</script>
