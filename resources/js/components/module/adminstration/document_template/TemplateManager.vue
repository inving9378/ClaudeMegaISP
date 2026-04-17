<template>
    <div
        class="modal fade"
        id="modalDocumentTemplates"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel"
    >
        <div class="modal-dialog modal-xl" id="modalDocumentTemplates_toChange">
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
                        <div
                            class="container"
                            id="crud_template_manager_container"
                        >
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
                                    }"
                                    @update-field="updateThisField"
                                    @update-show-load-button="
                                        hideButtonLoadAndSowPreviewButton
                                    "
                                    module="DocumentTemplate"
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
                            Guardar
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
import { requestFieldsByModule,requestEditedFieldsById } from "../../../../helpers/Request";
import ComponentFormDefault from "../../../ComponentFormDefault";
import { errorTextKeys, cleanHtml } from "../../client/info/comun_variable";
import TextTemplate from "../../../../shared/TextTemplate.vue";
import { resetDatatable } from "../../../../helpers/filters";
import { action } from "./helper";

export default {
    name: "TemplateManager",
    props: {},
    components: {
        ComponentFormDefault,
        TextTemplate,
    },
    setup(props, { emit }) {
        const title = ref("Generar Plantilla");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const html = ref(null);
        const showLoadButton = ref(true);
        const showPreviewButton = ref(false);
        const urlForm = ref("");

        onMounted(async () => {});

        watch(action, () => {
            if (action.value == "/administracion/document_template/add") {
                title.value = "Generar Plantilla";
                getfieldsJson("DocumentTemplate");
            } else {
                title.value = "Editar Plantilla";
                let id = getIdByAction(action.value);
                getfieldsEdited("DocumentTemplate", id);
            }
        });

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
        };

        const getfieldsEdited = async (model, id) => {
            fieldsJson.value = await requestEditedFieldsById(model, id);
            dataForm.data = new Form(fieldsJson.value);
        };

        const getIdByAction = (action) => {
            return _.trimStart(
                action,
                "/administracion/document_template/update/"
            );
        };

        const closeModal = () => {
            getfieldsJson("DocumentTemplate");
            cleanHtml.value = true;
            resetDatatable.value = true;
            $(`#modalDocumentTemplates`).modal("hide");
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const onSubmit = () => {
            urlForm.value = action.value;
            dataForm.data.uploadFile(`${urlForm.value}`).then((response) => {
                if (response.status == "ok") {
                    toastr.success(
                        `Documento subido Satisfactoriamente`,
                        "Documento Cliente"
                    );
                    closeModal();
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
