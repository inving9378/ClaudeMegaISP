<template>
    <div class="modal fade" id="modalDocument"
         data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="staticBackdropLabel"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="
                            dataForm.data.errors.clear(
                                $event.target.name
                            )
                        "
                    @keydown="
                            dataForm.data.errors.clear(
                                $event.target.name
                            )
                        "
                >
                    <div class="modal-body m-0">
                        <div class="container">
                            <div class="row">
                                <div class="col-12" v-if="dataForm.data.showResponseForbidden()">
                                    <div v-html="dataForm.data.showResponseForbidden()"></div>
                                </div>
                                <div class="col-12" v-else>
                                    <template v-for="val in fieldsJson">
                                        <ComponentFormDefault
                                            v-if="val.include"
                                            :id="idCrm"
                                            :json="val"
                                            :errors="dataForm.data.errors"
                                            :key="val"
                                            v-model="dataForm.data[val.field]"
                                            @update-field="updateThisField"
                                            @clear-error="clearError"
                                        />
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-light waves-effect"
                            data-bs-dismiss="modal"
                        >
                            Cerrar
                        </button>
                        <button
                            class="btn btn-primary"
                            type="submit"
                            :disabled="
                                                dataForm.data.errors.any()
                                            "
                        >
                            Subir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import {onMounted, reactive, ref, watch} from "vue";
import Form from "../../../../helpers/Form";
import {requestEditedFieldsById, requestFieldsByModule} from "../../../../helpers/Request";
import ComponentFormDefault from "../../../ComponentFormDefault";

export default {
    name: "UploadDocumentCrm",
    props: {
        module: {
            type: String,
            default: null,
        },
        action: String,
        idCrm: {
            type: String,
            default: null,
        },
    },
    components: {
        ComponentFormDefault
    },
    setup(props, {emit}) {
        const title = ref("Subir Documento");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            initComponent(props.action);
        });

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent(action);
            }
        );

        const initComponent = async (action) => {
            action == `add/${props.idCrm}`
                ? await getfieldsJson("DocumentCrm")
                : await getfieldsEdited(
                "DocumentCrm",
                props.action
                );
        };

        const getfieldsEdited = async (model, action) => {
            let id = action.substr(7);
            fieldsJson.value = await requestEditedFieldsById(model, id);
            dataForm.data = new Form(fieldsJson.value);
            title.value = "Editar Documento";
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
            title.value = "Subir Documento";
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const updateThisField = ({field, value}) => {
            dataForm.data[field] = value;
        };

        const clearError = ({field}) => {
            dataForm.data.errors.clear(field)
        }

        const onSubmit = () => {
            dataForm.data
                .uploadFile(`/crm/document/${props.action}`)
                .then((response) => {
                    toastr.success(
                        `Documento subido Satisfactoriamente`,
                        "Documento Crm"
                    );
                    emit("cleanModal");
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            updateThisField,
            clearError,
            closeModal,
            title
        };
    },
};
</script>

<style scoped></style>
