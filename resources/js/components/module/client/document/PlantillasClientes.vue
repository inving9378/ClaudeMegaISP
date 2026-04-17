<template>
    <div
        class="modal fade"
        id="modalDocumentPlantillas"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel"
    >
        <div
            class="modal-dialog modal-xl"
            id="modalDocumentPlantillas_toChange"
        >
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="closeModal"
                    ></button>
                </div>
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="dataForm.data.errors.clear($event.target.name)"
                    @keydown="dataForm.data.errors.clear($event.target.name)"
                >
                    <div class="modal-body m-0">
                        <div class="container">
                            <div class="row">
                                <div
                                    class="col-12"
                                    v-if="dataForm.data.showResponseForbidden()"
                                >
                                    <div
                                        v-html="
                                            dataForm.data.showResponseForbidden()
                                        "
                                    ></div>
                                </div>
                                <div class="col-12" v-else>
                                    <template v-for="val in fieldsJson">
                                        <ComponentFormDefault
                                            v-if="val.include"
                                            :id="idClient"
                                            :json="val"
                                            :errors="dataForm.data.errors"
                                            :key="val"
                                            v-model="dataForm.data[val.field]"
                                            @update-field="updateThisField"
                                            @clear-error="clearError"
                                        />
                                    </template>
                                </div>
                                <TextTemplate
                                    :errors="dataForm.data.errors"
                                    :property="{
                                        field: 'html',
                                        template: dataForm.data.template,
                                        idClient: idClient,
                                    }"
                                    module="Client"
                                    @update-field="updateThisField"
                                    @update-show-load-button="
                                        hideButtonLoadAndSowPreviewButton
                                    "
                                >
                                </TextTemplate>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-light waves-effect"
                            data-bs-dismiss="modal"
                            @click="closeModal"
                        >
                            Cerrar
                        </button>
                        <button
                            class="btn btn-primary"
                            type="submit"
                            :disabled="dataForm.data.errors.any()"
                        >
                            Generar
                        </button>

                        <button
                            v-if="showLoadButton"
                            class="btn btn-primary"
                            type="button"
                            id="load-content"
                            :disabled="dataForm.data.errors.any()"
                        >
                            Cargar
                        </button>
                        <button
                            v-if="showPreviewButton"
                            class="btn btn-primary"
                            type="button"
                            id="show-preview"
                            :disabled="dataForm.data.errors.any()"
                        >
                            Previsualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import Form from "../../../../helpers/Form";
import { requestFieldsByModule } from "../../../../helpers/Request";
import ComponentFormDefault from "../../../ComponentFormDefault";
import { errorTextKeys, cleanHtml } from "../info/comun_variable";
import { resetDatatable } from "../../../../helpers/filters";
import TextTemplate from "../../../../shared/TextTemplate.vue";

export default {
    name: "ClientTemplates",
    props: {
        module: {
            type: String,
            default: null,
        },
        action: String,
        idClient: {
            type: String,
            default: null,
        },
    },
    components: {
        ComponentFormDefault,
        TextTemplate,
    },
    setup(props, { emit }) {
        const title = ref("Generar Contrato");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const html = ref(null);
        const showLoadButton = ref(true);
        const showPreviewButton = ref(false);

        onMounted(() => {
            $(document).on("click", `#generateContract`, function (e) {
                getfieldsJson("DocumentTemplateClient");
                $(`#modalDocumentPlantillas`).modal("show");
            });
        });

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
            title.value = "Generar Contrato";
        };

        const closeModal = async () => {
            getfieldsJson("DocumentTemplateClient");
            cleanHtml.value = true;
            resetDatatable.value = true;
            $(`#modalDocumentPlantillas`).modal("hide");
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const onSubmit = async () => {
            dataForm.data
                .uploadFile(
                    `/cliente/document/generate_contract/${props.idClient}`
                )
                .then(async (response) => {
                    if (response.status == "ok") {
                        await closeModal();
                        toastr.success(
                            `Documento subido Satisfactoriamente`,
                            "Documento Cliente"
                        );
                        showContract(response.file_path);
                    }

                    if (response.status == "fail") {
                        toastr.error("Ha ocurrido un error", "Error");
                        errorTextKeys.value = response.keys;
                    }
                });
        };
        const hideButtonLoadAndSowPreviewButton = () => {
            showPreviewButton.value = true;
        };

        const showContract = (path) => {
            window.open(path, "_blank");
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            updateThisField,
            clearError,
            closeModal,
            title,
            html,
            showLoadButton,
            showPreviewButton,
            hideButtonLoadAndSowPreviewButton,
        };
    },
};
</script>

<style scoped></style>
