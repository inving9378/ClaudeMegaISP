<template>
    <div
        class="modal fade"
        id="modaltransaction"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <div class="modal-body m-0">
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <div v-for="val in fieldsJson">
                            <ComponentFormDefaultTransaction
                                v-if="val.include"
                                :id="idClient"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </div>

                        <div class="form-group text-center">
                            <a class="btn btn-secondary me-3"
                               href="javascript:void(0)"
                               @click="closeModal"
                            >
                                Cerrar
                            </a>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="dataForm.data.errors.any()"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, ref, watch, onMounted } from "vue";
import {requestEditedFieldsById, requestFieldsByModule} from "../../../../../helpers/Request";
import Form from "../../../../../helpers/Form";
import ComponentFormDefaultTransaction from "./component-form/ComponentFormDefaultTransaction";

export default {
    name: "ClientCrudTransaction",
    props: {
        module: {
            type: String,
            default: null,
        },
        idClient: {
            type: String,
            default: null,
        },
        action: String,
    },
    components: {ComponentFormDefaultTransaction },
    setup(props, { emit }) {
        const title = ref("Crear Transaction");
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
            action == `crear/${props.idClient}`
                ? await getfieldsJson("ClientTransaction")
                : await getfieldsEdited(
                "ClientTransaction",
                props.action
                );
        };

        const getfieldsEdited = async (model, action) => {
            let id = action.substr(7);
            fieldsJson.value = await requestEditedFieldsById(model, id);
            dataForm.data = new Form(fieldsJson.value);
            title.value = "Editar Transaction";
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
            title.value = "Crear Transaction";
        };

        const onSubmit = () => {
            dataForm.data
                .uploadFile(
                    `/cliente/billing/transaction/${props.action}`,
                    title.value == 'Crear Transaction' ? 'reset' : 'editar'
                )
                .then((response) => {
                    toastr.success("Transaction");
                    emit("cleanModal");
                });
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({field}) => {
            dataForm.data.errors.clear(field)
        }

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
