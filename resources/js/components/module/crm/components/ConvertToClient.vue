<template>
    <div class="row">
        <div>
            <h4>Convertir a Cliente</h4>
        </div>
        <div class="col-12">
            <button
                type="button"
                class="btn btn-outline-info me-2"
                style="position: relative; float: right"
                @click="convertToClient(id)"
            >
                Convertir
            </button>
        </div>
        <div class="col-xl-6">
            <div class="card-header">
                <h6>Informacion Principal</h6>
            </div>
            <div class="card-body" v-for="f in ['client_main_information']">
                <template v-for="val in fieldsJson[f]">
                    <ComponentFormDefault
                        v-if="val.include"
                        :id="id"
                        :json="val"
                        :errors="dataFormClient.data.errors"
                        :key="val"
                        v-model="dataFormClient.data[val.field]"
                        @update-field="updateThisField"
                        @clear-error="clearError"
                    />
                </template>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card-header">
                <h6>Informacion Adicional</h6>
            </div>
            <div
                class="card-body"
                v-for="f in ['client_additional_information']"
            >
                <template v-for="val in fieldsJson[f]">
                    <ComponentFormDefault
                        v-if="val.include"
                        :id="id"
                        :json="val"
                        :errors="dataFormClient.data.errors"
                        :key="val"
                        v-model="dataFormClient.data[val.field]"
                        @update-field="updateThisField"
                        @clear-error="clearError"
                    />
                </template>
            </div>
        </div>
        <div class="col-12 mb-2">
            <button
                type="button"
                class="btn btn-outline-info me-2"
                style="position: relative; float: right"
                @click="convertToClient(id)"
            >
                Convertir
            </button>
        </div>
    </div>
</template>

<script>
import ComponentFormDefault from "../../../ComponentFormDefault.vue";
import ModalCentrado from "../../../../shared/ModalCentrado.vue";
import axios from "axios";
import { onMounted, reactive, ref } from "vue";
import Form from "../../../../helpers/Form";
import { requestFieldsByModule } from "../../../../helpers/Request";
import { arrayOfObjectToArrayOfArray } from "../../../../helpers/Transform";
import {
    fieldsJson,
    dataForm,
    clearError,
    updateThisField,
    getfieldsEditedWithMultipleModel,
} from "../../../../hook/crudHook";

export default {
    name: "ConvertToClient",
    components: { ComponentFormDefault, ModalCentrado },
    props: {
        id: String,
    },
    emits: ["update-field", "clear-error"],
    setup(props) {
        const disabled = ref(false);
        const fieldsJson = ref({});
        const fields = ref([]);
        const dataFormClient = reactive({
            data: new Form({}),
        });

        onMounted(async () => {
            let ids = await getCrmMainInformationIdAndCrmLeadInformationId(
                props.id
            );

            await getfieldsEditedWithMultipleModel(
                [
                    { main_information: "CrmMainInformation" },
                    { lead_information: "CrmLeadInformation" },
                ],
                props.id,
                {
                    main_information: ids.crmMainInformationId,
                    lead_information: ids.crmLeadInformationId,
                }
            );
            await getfieldsWithMultipleModel([
                { client_main_information: "ClientMainInformation" },
                {
                    client_additional_information:
                        "ClientAdditionalInformation",
                },
            ]);
        });

        const getCrmMainInformationIdAndCrmLeadInformationId = async (
            crmId
        ) => {
            let res;
            await axios
                .post(
                    `/crm/information/${crmId}/get-crm-main-information-id-and-crm-lead-information-id`
                )
                .then((r) => {
                    res = r.data;
                });

            return {
                crmMainInformationId: res.crmMainInformationId,
                crmLeadInformationId: res.crmLeadInformationId,
            };
        };

        const getfieldsWithMultipleModel = async (model) => {
            let counter = 0;
            let localFieldsJson = [];
            for (let variable of model) {
                let key = Object.keys(variable);
                let result = await requestFieldsByModule(variable[key]);
                fields.value[counter++] = result;
                localFieldsJson[key] = result;
            }
            // assign field to key in array
            let allFields = _.mapKeys(
                _.flattenDeep(arrayOfObjectToArrayOfArray(fields.value)),
                (v) => v.field
            );

            // asign value to field before render In ComponentFormDefault
            allFields = await assignValueFormCRMData(allFields);
            allFields =
                await assignValueForSelect2EstadoMunicipioColoniaComponent(
                    allFields
                );
            dataFormClient.data = new Form(allFields);
            fieldsJson.value = localFieldsJson;
        };

        const assignValueFormCRMData = (allFields) => {
            for (let key in allFields) {
                if (dataForm.data[key] && key != "colony_id") {
                    allFields[key].value = dataForm.data[key];
                }
            }
            return allFields;
        };

        const assignValueForSelect2EstadoMunicipioColoniaComponent = (
            allFields
        ) => {
            let json = {};
            if (dataForm.data["state_id"]) {
                json.state_id = dataForm.data["state_id"];
            }
            if (dataForm.data["municipality_id"]) {
                json.municipality_id = dataForm.data["municipality_id"];
            }
            if (dataForm.data["colony_id"]) {
                json.colony_id = _.toInteger(dataForm.data["colony_id"]);
            }
            allFields["colony_id"].default_value = json;
            return allFields;
        };

        const updateThisField = ({ field, value }) => {
            dataFormClient.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataFormClient.data.errors.clear(field);
        };

        const convertToClient = async (crmId) => {
            disabled.value = true;
            await dataFormClient.data
                .submit("post", `/crm/convert-to-client/${crmId}`, "edit")
                .then((response) => {
                    toastr.success(
                        "Crm Convertido a Cliente Satisfactoriamente"
                    );
                    setTimeout(() => {
                        location.href = `/cliente/editar/${response}`;
                    }, 1000);
                });
            disabled.value = false;
        };

        return {
            fieldsJson,
            dataForm,
            fields,
            dataFormClient,

            updateThisField,
            clearError,
            disabled,
            convertToClient,
        };
    },
};
</script>

<style scoped></style>
