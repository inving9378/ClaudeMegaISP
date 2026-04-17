<template>
    <div
        class="modal fade"
        id="modalTicketEdit"
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
                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :id="id"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

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
                                {{ submitButtonAction }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ComponentFormDefault from "../../../ComponentFormDefault";
import {onMounted, reactive, ref, watch} from "vue";
import { requestEditedFieldsByIdInMessage } from "../helper/request";
import Form from "../../../../helpers/Form";
export default {
    name: "TicketModalEdit",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
    },
    components: {
        ComponentFormDefault,
    },
    setup(props,{ emit }) {
        const title = ref("Editar Mensaje");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        let submitButtonAction ='Salvar Mensaje';

        onMounted(() => {
            initComponent(props.id);
        });

        watch(
            () => props.id,
            (id, idBefore) => {
                initComponent(id);
            }
        );

        const initComponent = async (id) => {
            if(id) await getfieldsEditedInModal("TicketThread", id);
        }

        const getfieldsEditedInModal = async (model, id) => {
            fieldsJson.value = await requestEditedFieldsByIdInMessage(model, id);
            dataForm.data = new Form(fieldsJson.value);
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

       const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const onSubmit = () => {
            dataForm.data
                .uploadFile(
                    `/tickets/mensaje/${props.action}`,
                    title.value = 'Editar Mensaje'
                )
                .then((response) => {
                    toastr.success(
                        "Mensaje Editado Satisfactoriamente"
                    );
                    emit("cleanModal");
                });
        };
        return {
            title,
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            closeModal
        };
    },
};
</script>

<style scoped></style>
