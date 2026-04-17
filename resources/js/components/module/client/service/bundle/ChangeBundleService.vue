<template>
    <div
        class="modal fade"
        id="modalbundleserviceChange"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel"
        tabindex="-1"
        aria-hidden="true"
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
                                fieldsJson['init']['bundle_id']
                            "
                            :key="fieldsJson['init']['bundle_id']"
                            :property="fieldsJson['init']['bundle_id']"
                            :errors="dataForm.data.errors"
                            @click="clearError({ field: 'bundle_id' })"
                            v-model="dataForm.data['bundle_id']"
                            @update-field="updateThisField"
                        />
                        <br />

                        <div class="card p-3" v-show="show">
                            <h4 class="card-title text-start">
                                Opciones del Servicio de Paquete
                            </h4>
                            <br />

                            <template
                                v-for="val in fieldsJson[
                                    'bundle_service_option'
                                ]"
                            >
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

                        <div class="card p-3" v-show="show">
                            <h4 class="card-title text-start">
                                Informacion de Contrato
                            </h4>
                            <br />
                            <template
                                v-for="val in fieldsJson[
                                    'contract_information'
                                ]"
                            >
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
import { requestFieldsByModule } from "../../../../../helpers/Request";
import ComponentFormDefault from "../../../../ComponentFormDefault";
import SelectComponent from "../../../../../shared/SelectComponent";
import {
    getPlansEqualsForByBundleId,
    getPlansForByBundleId,
    requestEditedBundlePlan,
    requestEditedBundlePlanToChange,
} from "../../helpers/helper";
import { changeBalance } from "../../info/comun_variable";

export default {
    name: "ChangeBundleService",
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
    emits: ["cleanModal"],
    setup(props, { emit }) {
        const disabledComponentInEdit = ref(false);
        const show = ref(false);
        const title = ref("Cambiar Tarifa");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const idModel = ref(null);

        const internetService = ref({});
        const vozService = ref({});
        const customService = ref({});
        const oldIdBundle = ref(null);

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
            await getfieldsEditedBundleService(
                "ClientBundleService",
                props.action
            );
        };

        const getfieldsEditedBundleService = async (model, action) => {
            let id = action.substr(7);
            idModel.value = id;
            let result = await requestEditedBundlePlanToChange(id);

            fieldsJson.value = result["fields"];

            dataForm.data = new Form(fieldsJson.value);

            oldIdBundle.value = dataForm.data["bundle_id"];

            let val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["bundle_service_option"] = _.mapKeys(
                val["bundle_service_option"],
                (v) => v.field
            );
            val["contract_information"] = _.mapKeys(
                val["contract_information"],
                (v) => v.field
            );
            fieldsJson.value = val;
            show.value = true;
            title.value = "Cambiar Tarifa";
            disabledComponentInEdit.value = true;
        };

        const updateThisField = ({ field, value }) => {
            field == "bundle_id"
                ? requestPlanForBundle(value)
                : (dataForm.data[field] = value);
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const requestPlanForBundle = async (id) => {
            let result = await getPlansEqualsForByBundleId(id.value, props.idClient);
            fieldsJson.value = result["fields"];
            dataForm.data = new Form(fieldsJson.value);

            let val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["bundle_service_option"] = _.mapKeys(
                val["bundle_service_option"],
                (v) => v.field
            );
            val["contract_information"] = _.mapKeys(
                val["contract_information"],
                (v) => v.field
            );

            fieldsJson.value = val;
            show.value = true;
        };

        const onSubmit = async () => {
            dataForm.data
                .submit(
                    "post",
                    `/cliente/clientbundleservice/bundle/change-bundle/${idModel.value}`,
                    props.action
                )
                .then((response) => {
                    changeBalance.value = true;
                    toastr.success(
                        `Cliente ${
                            props.idClient ? "actualizado" : "creado"
                        } satisfactoriamente`,
                        "Cliente con Servicio de Bundle"
                    );
                    redirectToCurrentURL();
                    emit("cleanModal");
                });
        };

        const redirectToCurrentURL = () => {
            let url = window.location.href;
            window.location.href = url;
        };

        const closeModal = () => {
            $("#modalbundleserviceChange").modal("hide");
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
            internetService,
            vozService,
            customService,
            idModel,
        };
    },
};
</script>

<style scoped></style>
