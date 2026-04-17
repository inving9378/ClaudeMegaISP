<template>
    <div
        class="modal fade"
        id="modalcustomservice"
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
                                fieldsJson['init']['custom_id']
                            "
                            :key="fieldsJson['init']['custom_id']"
                            :property="fieldsJson['init']['custom_id']"
                            :errors="dataForm.data.errors"
                            @click="clearError({ field: 'custom_id' })"
                            v-model="dataForm.data['custom_id']"
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

                            <hr class="mb-2" />
                            <h4 class="card-title text-center">
                                Opciones de tarifas
                            </h4>
                            <br />

                            <template v-for="val in fieldsJson['other']">
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
    requestFieldsByModule,
    requestFieldsByModuleWithModuleRequested,
} from "../../../../../helpers/Request";
import ComponentFormDefault from "../../../../ComponentFormDefault";
import SelectComponent from "../../../../../shared/SelectComponent";
import { changeBalance } from "../../info/comun_variable";

export default {
    name: "ClientCrudCustomService",
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
        const title = ref("Crear Servicio de Custom");
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

        watch(
            () => props.render,
            () => {
                initComponent(props.action);
            }
        );

        const initComponent = async (action) => {
            action == `crear/${props.idClient}`
                ? await getfieldsJson("ClientCustomService")
                : await getfieldsEditedCustomService(
                      "ClientCustomService",
                      props.action
                  );
        };

        const getfieldsEditedCustomService = async (model, action) => {
            let id = action.substr(7);
            fieldsJson.value = await requestEditedFieldsById(model, id);
            dataForm.data = new Form(fieldsJson.value);

            let val;
            val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["other"] = _.mapKeys(val["other"], (v) => v.field);
            fieldsJson.value = val;

            show.value = true;
            title.value = "Editar Servicio de Custom";
            disabledComponentInEdit.value = true;
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);

            let val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["other"] = _.mapKeys(val["other"], (v) => v.field);
            fieldsJson.value = val;

            show.value = false;
            title.value = "Crear Servicio de Custom";
            disabledComponentInEdit.value = false;
        };

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    `/cliente/clientcustomservice/${props.action}`,
                    props.action
                )
                .then((response) => {
                    changeBalance.value = true;
                    toastr.success(
                        `Cliente ${
                            props.idClient ? "actualizado" : "creado"
                        } satisfactoriamente`,
                        "Cliente con Servicio de Custom"
                    );
                    emit("cleanModal");
                });
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const updateThisField = ({ field, value }) => {
            field == "custom_id"
                ? getSelectedFieldsbyRequestedModule(value)
                : (dataForm.data[field] = value);
        };

        const getSelectedFieldsbyRequestedModule = async (id) => {
            fieldsJson.value = await requestFieldsByModuleWithModuleRequested(
                "ClientCustomService",
                "App\\Models\\Custom",
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
