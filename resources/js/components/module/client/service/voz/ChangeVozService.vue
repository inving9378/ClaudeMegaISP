<template>
    <div
        class="modal fade"
        id="modalvozserviceChange"
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
                        <SelectComponent
                            v-if="
                                fieldsJson['init'] &&
                                fieldsJson['init']['voz_id']
                            "
                            :key="fieldsJson['init']['voz_id']"
                            :property="fieldsJson['init']['voz_id']"
                            :errors="dataForm.data.errors"
                            @click="clearError({ field: 'voz_id' })"
                            v-model="dataForm.data['voz_id']"
                            @update-field="updateThisField"
                        />

                        <div v-show="show">
                            <template v-for="val in fieldsJson['init']">
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

                        <div class="form-group text-center">
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
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, ref, watch, onMounted } from "vue";

import Form from "../../../../../helpers/Form";
import {
    requestEditedFieldsById,
    requestFieldsByModuleWithModuleRequested,
} from "../../../../../helpers/Request";
import ComponentFormDefault from "../../../../ComponentFormDefault";
import SelectComponent from "../../../../../shared/SelectComponent";
import { changeBalance } from "../../info/comun_variable";

export default {
    name: "ChangeVozService",
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
        render: Number,
    },
    components: {
        ComponentFormDefault,
        SelectComponent,
    },
    setup(props, { emit }) {
        const disabledComponentInEdit = ref(false);
        const show = ref(false);
        const title = ref("Cambiar Servicio de Voz");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const idModel = ref(null);

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent(action);
            }
        );

        watch(
            () => props.render,
            () => {
                initComponent(props.action);
            }
        );

        const initComponent = async (action) => {
            await getfieldsEditedVozService("ClientVozService", props.action);
        };

        const getfieldsEditedVozService = async (model, action) => {
            let id = action.substr(7);
            idModel.value = id;
            fieldsJson.value = await requestEditedFieldsById(model, id);
            dataForm.data = new Form(fieldsJson.value);

            let val;
            val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            fieldsJson.value = val;

            show.value = true;
            title.value = "Cambiar Servicio de Voz";
            disabledComponentInEdit.value = true;
        };

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    `/cliente/clientvozservice/change-voz/${idModel.value}`,
                    props.action
                )
                .then((response) => {
                    changeBalance.value = true;
                    toastr.success(
                        `Cliente actualizado satisfactoriamente`,
                        "Cliente con Servicio de Voz"
                    );
                    emit("cleanModal");
                });
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const updateThisField = ({ field, value }) => {
            field == "voz_id"
                ? getSelectedFieldsbyRequestedModule(value)
                : (dataForm.data[field] = value);
        };

        const getSelectedFieldsbyRequestedModule = async (id) => {
            fieldsJson.value = await requestFieldsByModuleWithModuleRequested(
                "ClientVozService",
                "App\\Models\\Voise",
                id.value,
                props.idClient
            );
            dataForm.data = new Form(fieldsJson.value);

            let val;
            val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["other"] = _.mapKeys(val["other"], (v) => v.field);
            fieldsJson.value = val;

            show.value = true;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            updateThisField,
            clearError,
            show,
            closeModal,
            title,
            disabledComponentInEdit,
        };
    },
};
</script>

<style scoped></style>
