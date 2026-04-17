<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="dataForm.data.errors.clear($event.target.name)"
                    @keydown="dataForm.data.errors.clear($event.target.name)"
                >
                    <div class="mt-3">
                        <div class="card">
                            <div
                                class="card-header d-flex justify-content-between"
                            >
                                <div></div>
                                <div>
                                    <button
                                        v-if="
                                            hasPermission.data.canView(
                                                'crm_convert_crm'
                                            )
                                        "
                                        type="button"
                                        class="btn btn-outline-info me-2"
                                        :disabled="disabled"
                                        @click="covertToCRM"
                                    >
                                        Convertir
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-info"
                                        @click.prevent="onSubmit"
                                    >
                                        Salvar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-content-center">
                        <div class="">
                            <div class="row">
                                <div class="col-xl-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6>Información Principal</h6>
                                        </div>
                                        <div
                                            class="p-4 m-2 h-fix-content shadow-low"
                                        >
                                            <div class="form-group row">
                                                <label
                                                    class="col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center"
                                                >
                                                    ID:
                                                </label>
                                                <div
                                                    class="col-sm-12 col-md-6 d-flex align-items-center"
                                                >
                                                    {{ id }}
                                                </div>
                                            </div>

                                            <template
                                                v-for="val in fieldsJson[
                                                    'main_information'
                                                ]"
                                            >
                                                <ComponentFormDefault
                                                    v-if="val.include"
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    :key="val"
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6>Datos geográficos</h6>
                                        </div>
                                        <div class="p-4 m-2 border shadow-low">
                                            <h6>Mapa</h6>
                                            <br />
                                            <InputModalWithGoogleMap
                                                :position="
                                                    dataForm.data.geodata
                                                "
                                                :address="dataForm.data.address"
                                                @change-coordinate="
                                                    (val) =>
                                                        (dataForm.data.geodata =
                                                            val)
                                                "
                                            />
                                            <br />
                                        </div>
                                    </div>
                                    <div class="card">
                                        <div class="card-header">
                                            <h6>
                                                Informacion del Cliente
                                                Potencial
                                            </h6>
                                        </div>
                                        <div
                                            class="p-4 m-2 h-fix-content shadow-low"
                                        >
                                            <template
                                                v-for="val in fieldsJson[
                                                    'lead_information'
                                                ]"
                                            >
                                                <ComponentFormDefault
                                                    v-if="val.include"
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    :key="val"
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group text-center mb-1">
                        <a class="btn btn-secondary me-2" href="/crm">
                            Atras
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
        <div class="row">
            <CrmRecentActivity
                :id="id"
                @show-information="showInformation"
            ></CrmRecentActivity>
        </div>
    </div>

    <div
        class="modal fade modal-center modal-activity"
        tabindex="-1"
        role="dialog"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Informacion</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div class="modal-body">
                    <div class="m-1 p-2" v-for="(text, key) in textInformation">
                        <div class="col-12 text-center">
                            <strong>{{ key }}:</strong>
                        </div>
                        <div
                            class="col-12 overflow-auto"
                            v-html="JSON.stringify(text)"
                        ></div>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>

    <input type="hidden" id="module_id" :value="id" />
    <!-- /.modal -->
</template>

<script setup>
import { onBeforeMount, reactive, ref } from "vue";
import {
    fieldsJson,
    dataForm,
    clearError,
    updateThisField,
    getfieldsEditedWithMultipleModel,
} from "../../../hook/crudHook";
import ComponentFormDefault from "../../ComponentFormDefault";
import InputModalWithGoogleMap from "../../../shared/InputModalWithGoogleMap";
import Permission from "../../../helpers/Permission";
import NotificationCrm from "./components/NotificationCrm";
import CrmRecentActivity from "./components/CrmRecentActivity";
import { allViewHasPermission } from "../../../helpers/Request";
import { updateLastContacted } from "./helpers/helper";
import ConvertToClient from "./components/ConvertToClient.vue";

defineOptions({ name: "InformationCrmCrud" });

const props = defineProps({
    action: String,
    id: {
        type: String,
        default: null,
    },
});
let submitButtonAction = "Salvar CRM";
const reloadComponent = ref(0);
const disabled = ref(false);
const hasPermission = reactive({
    data: new Permission({}),
});
const textInformation = ref("");

onBeforeMount(async () => {
    if (props.id) {
        hasPermission.data = new Permission(await allViewHasPermission());

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
        updateLastContacted(props.id);
    }
});

const getCrmMainInformationIdAndCrmLeadInformationId = async (crmId) => {
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

const onSubmit = () => {
    dataForm.data
        .submit("post", `/crm/${props.action}`, props.action)
        .then((response) =>
            toastr.success("Crm Actualizado Satisfactoriamente", "Crm")
        );
};

const covertToCRM = async () => {
    if (confirm("¿Estas seguro de convertir este crm a cliente?")) {
        returnViewToConvertCrmToCLient();
    }
};

const returnViewToConvertCrmToCLient = () => {
    location.href = `/crm/view-of-convert-crm-to-client/${props.id}`;
};

const showInformation = (info) => {
    textInformation.value = info;
};
</script>

<style scoped></style>
