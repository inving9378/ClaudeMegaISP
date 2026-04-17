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
                            <div class="card-header">
                                <div
                                    class="row customer-billing-sticky-sidebar spl-sticky-sidebar"
                                >
                                    <div
                                        class="col-lg-12 customer-billing-sticky-sidebar-inner"
                                    >
                                        <div class="panel panel-default">
                                            <div class="panel-body">
                                                <div class="row">
                                                    <ClientInfoAccountBalance
                                                        v-if="id"
                                                        :client_id="id"
                                                    ></ClientInfoAccountBalance>
                                                    <div
                                                        class="col-lg-5 col-md-5 col-sm-5 text-end"
                                                    >
                                                        <div
                                                            class="float-right customer-buttons-wrapper"
                                                        >
                                                            <div
                                                                v-if="
                                                                    clientMainInformationId
                                                                "
                                                                class="dropdown d-inline-block me-2"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-outline-secondary waves-effect waves-light"
                                                                    data-bs-toggle="dropdown"
                                                                    aria-haspopup="true"
                                                                    aria-expanded="false"
                                                                >
                                                                    <span
                                                                        class="ms-1"
                                                                        >Tareas</span
                                                                    >
                                                                    <i
                                                                        class="mdi mdi-chevron-down d-none d-xl-inline-block"
                                                                    ></i>
                                                                </button>
                                                                <div
                                                                    class="dropdown-menu dropdown-menu-end"
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-outline-info waves-effect waves-light dropdown-item"
                                                                        data-bs-target="#crudTask"
                                                                        data-bs-toggle="modal"
                                                                    >
                                                                        <span
                                                                            >Agregar
                                                                            Tarea</span
                                                                        >
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <div
                                                                class="dropdown d-inline-block me-2"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-outline-secondary waves-effect waves-light"
                                                                    data-bs-toggle="dropdown"
                                                                    aria-haspopup="true"
                                                                    aria-expanded="false"
                                                                >
                                                                    <span
                                                                        class="ms-1"
                                                                        >Soporte</span
                                                                    >
                                                                    <i
                                                                        class="mdi mdi-chevron-down d-none d-xl-inline-block"
                                                                    ></i>
                                                                    <span
                                                                        class="badge bg-danger rounded-pill ms-1"
                                                                        v-text="
                                                                            clientTickets.open
                                                                        "
                                                                    ></span>
                                                                </button>
                                                                <div
                                                                    class="dropdown-menu dropdown-menu-end"
                                                                >
                                                                    <a
                                                                        class="dropdown-item"
                                                                        :href="`/tickets/crear/${id}`"
                                                                    >
                                                                        Crear
                                                                        Ticket</a
                                                                    >
                                                                    <a
                                                                        class="dropdown-item"
                                                                        :href="`/tickets/abiertos/${id}`"
                                                                    >
                                                                        Abiertos
                                                                        <span
                                                                            class="badge bg-danger rounded-pill ms-1"
                                                                            v-text="
                                                                                clientTickets.open
                                                                            "
                                                                        ></span
                                                                    ></a>
                                                                    <a
                                                                        class="dropdown-item"
                                                                        :href="`/tickets/cerrados/${id}`"
                                                                    >
                                                                        Cerrados
                                                                        <span
                                                                            class="badge bg-success rounded-pill ms-1"
                                                                            v-text="
                                                                                clientTickets.closed
                                                                            "
                                                                        ></span
                                                                    ></a>
                                                                </div>
                                                            </div>

                                                            <button
                                                                class="btn btn-primary"
                                                                type="submit"
                                                                :disabled="
                                                                    dataForm.data.errors.any()
                                                                "
                                                            >
                                                                {{
                                                                    submitButtonAction
                                                                }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-content-center">
                        <div class="col-md-12">
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

                                            <ComponentFormDefault
                                                v-for="val in fieldsJson[
                                                    'client_main_information'
                                                ]"
                                                :id="id"
                                                :json="val"
                                                :errors="dataForm.data.errors"
                                                :key="val"
                                                v-model="
                                                    dataForm.data[val.field]
                                                "
                                                @update-field="updateThisField"
                                                @clear-error="clearError"
                                            />
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
                                            <InputModalWithGoogleMapForm
                                                :property="{
                                                    field: 'geodata',
                                                    label: '',
                                                    class_col: 'full',
                                                    class_field: 'form-control',
                                                    class_label:
                                                        'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                                                }"
                                                :key="dataForm.data.geodata"
                                                :modelValue="
                                                    dataForm.data.geodata
                                                "
                                                :errors="dataForm.data.errors"
                                                @update-field="updateThisField"
                                            />
                                            <br />
                                        </div>
                                    </div>
                                    <div class="card">
                                        <div class="card-header">
                                            <h6>Información Adicional</h6>
                                        </div>

                                        <div
                                            class="p-4 m-2 h-fix-content shadow-low"
                                        >
                                            <div
                                                class="col-12 row mb-2 bg-warning"
                                                v-if="
                                                    fieldsJson[
                                                        'client_additional_information'
                                                    ]
                                                "
                                            >
                                                <label
                                                    class="col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center"
                                                >
                                                    Nomenclatura Antigua:
                                                </label>
                                                <div
                                                    class="col-sm-12 col-md-6 d-flex align-items-center"
                                                >
                                                    {{
                                                        fieldsJson[
                                                            "client_additional_information"
                                                        ][
                                                            "box_nomenclator_old"
                                                        ]["value"]
                                                    }}
                                                </div>
                                            </div>
                                            <template
                                                v-for="val in fieldsJson[
                                                    'client_additional_information'
                                                ]"
                                                :key="val"
                                            >
                                                <div
                                                    class="col-12 partial-class-field row mb-2"
                                                    v-if="
                                                        val.field === 'gpon_ont'
                                                    "
                                                >
                                                    <div class="input-group">
                                                        <label
                                                            for="gpon_ont"
                                                            class="d-md-block d-none col-md-3 col-form-label text-md-end pe-2"
                                                        >
                                                            GPON ONT
                                                        </label>

                                                        <input
                                                            type="text"
                                                            id="gpon_ont"
                                                            name="gpon_ont"
                                                            class="form-control col-sm-12 col-md-9 ms-1"
                                                            disabled
                                                            v-model="
                                                                dataForm.data[
                                                                    val.field
                                                                ]
                                                            "
                                                        />
                                                        <div>
                                                            <q-btn
                                                                label="Mirar / Fijar"
                                                                :loading="
                                                                    loadingOnu
                                                                "
                                                                no-caps
                                                                unelevated
                                                                padding="7px"
                                                                style="
                                                                    background: #e9e9ef;
                                                                "
                                                                @click="
                                                                    onFixedShow
                                                                "
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="col-12 partial-class-field row mb-2"
                                                    v-else-if="
                                                        val.field ===
                                                        'power_dbm'
                                                    "
                                                >
                                                    <label
                                                        for="power_dbm"
                                                        class="d-md-block d-none col-md-3 col-form-label text-md-end"
                                                    >
                                                        Potencia en dbm
                                                    </label>

                                                    <div
                                                        class="col-sm-12 col-md-5 ps-2"
                                                    >
                                                        <input
                                                            type="text"
                                                            id="power_dbm"
                                                            name="power_dbm"
                                                            class="form-control"
                                                            placeholder="Potencia técnico"
                                                            v-model="
                                                                dataForm.data[
                                                                    val.field
                                                                ]
                                                            "
                                                        />
                                                    </div>
                                                    <div
                                                        class="col-sm-12 col-md-4 ps-2"
                                                    >
                                                        <input
                                                            type="text"
                                                            id="olt_power_dbm"
                                                            name="olt_power_dbm"
                                                            class="form-control"
                                                            placeholder="Potencia OLT"
                                                            readonly
                                                            :value="
                                                                dataForm.data[
                                                                    'olt_power_dbm'
                                                                ] ??
                                                                'Desconocida'
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                                <ComponentFormDefault
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                    v-else-if="
                                                        val.field !==
                                                        'olt_power_dbm'
                                                    "
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group text-center">
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
        <div class="col-12">
            <div class="row">
                <ClientRecentActivity
                    :id="id"
                    @show-information="showInformation"
                ></ClientRecentActivity>
            </div>
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
                <div class="modal-body modal-xl">
                    <div class="col-lg-12 row mt-3">
                        <div class="col-lg-12">
                            <span>
                                <b>Fecha De Creación: </b>
                                {{ textInformation.created_at }}
                            </span>
                        </div>
                        <div class="col-lg-12 mt-2">
                            <span>
                                <b>Usuario: </b>
                                {{ textInformation.user }}
                            </span>
                        </div>
                        <div class="col-lg-12 mt-2 mb-2">
                            <span>
                                <b>Descripción: </b>
                                {{ textInformation.text }}
                            </span>
                        </div>

                        <div class="col-lg-6">
                            <span><h3>Atributos Antiguos</h3></span>
                            <div
                                v-for="attribute in oldAttributes"
                                :key="Object.keys(attribute)[0]"
                            >
                                <p
                                    v-for="(value, key) in attribute"
                                    :key="key"
                                    :class="{
                                        'highlight-difference': hasDifference(
                                            key,
                                            value
                                        ),
                                    }"
                                >
                                    <b>{{ key }}:</b> {{ value }}
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <span><h3>Atributos Nuevos</h3></span>
                            <div
                                v-for="attribute in newAttributes"
                                :key="Object.keys(attribute)[0]"
                            >
                                <p
                                    v-for="(value, key) in attribute"
                                    :key="key"
                                    :class="{
                                        'highlight-difference': hasDifference(
                                            key,
                                            value
                                        ),
                                    }"
                                >
                                    <b>{{ key }}:</b> {{ value }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>

    <input type="hidden" id="module_id" :value="id" />

    <div
        class="modal fade"
        id="crudTask"
        data-backdrop="static"
        data-keyboard="false"
        v-if="clientMainInformationId"
    >
        <div class="modal-dialog modal-dialog-slide-right">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Agregar Tarea</h6>
                </div>
                <TaskClients
                    action="/scheduling/task/add"
                    :customerId="clientMainInformationId"
                ></TaskClients>
            </div>
        </div>
    </div>

    <panel-onu
        :onu="currentOnu"
        :show="showPanelOnu"
        :from-form="true"
        :from-client="true"
        :has-permission="hasPermission"
        @hide="showPanelOnu = false"
        @removed="onRemoveOnu"
        @update="onUpdateCurrentOnu"
    />

    <dialog-unconfigured-onus
        :show="showDialogUnconfigured"
        :client="getClient()"
        :has-permission="hasPermission"
        @created="onCreatedOnu"
        @hide="showDialogUnconfigured = false"
    />
</template>

<script setup>
import { watch, onMounted, ref, reactive, onUnmounted } from "vue";
import {
    fieldsJson,
    dataForm,
    clearError,
    getfieldsEditedWithMultipleModel,
} from "../../../hook/crudHook";
import { getClientTickets } from "./helpers/helper";
import ComponentFormDefault from "../../ComponentFormDefault";
import ViewTopNameWithBalance from "./ViewTopNameWithBalance";
import ClientInfoAccountBalance from "./info/ClientInfoAccountBalance";

import DialogUnconfiguredOnus from "../olts/components/dialogs/DialogUnconfiguredOnus.vue";
import PanelOnu from "../olts/components/onus/PanelOnu.vue";

import InputModalWithGoogleMapForm from "../../../shared/InputModalWithGoogleMapForm";
import { changeBalance, clientMainInformationId } from "./info/comun_variable";
import TaskClients from "./helpers/TaskClients.vue";
import CrmRecentActivity from "../crm/components/CrmRecentActivity.vue";
import ClientRecentActivity from "./components/ClientRecentActivity.vue";
import Swal from "sweetalert2";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";
import { getOnuByClient, getSignal } from "../olts/helper/request";
import { formatDate } from "@fullcalendar/core/index.js";
import { message } from "../../../helpers/toastMsg";

defineOptions({
    name: "InformationClientCrud",
});

const props = defineProps({
    action: String,
    id: {
        type: String,
        default: null,
    },
});

const emits = defineEmits(["getTypeOfBilling"]);

let submitButtonAction = props.id && "Salvar Cliente";
const clientTickets = ref({
    open: 0,
    closed: 0,
});

const hasPermission = reactive({
    data: new Permission({}),
});

const oldStatus = ref(null);
const currentOnu = ref(null);
const showPanelOnu = ref(false);
const showDialogUnconfigured = ref(false);
let timer = null;
const loadingOnu = ref(false);

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    if (props.id) {
        clientTickets.value = await getClientTickets(props.id);
        let ids =
            await getClientMainInformationIdAndClientAdditionalInformationId(
                props.id
            );

        clientMainInformationId.value = ids.clientMainInformationId;

        await getfieldsEditedWithMultipleModel(
            [
                {
                    client_main_information: "ClientMainInformation",
                },
                {
                    client_additional_information:
                        "ClientAdditionalInformation",
                },
            ],
            props.id,
            {
                client_main_information: ids.clientMainInformationId,
                client_additional_information:
                    ids.clientAdditionalInformationId,
            }
        );
        oldStatus.value = dataForm.data.estado;
        emits("getTypeOfBilling", {
            typeOfBilling: dataForm.data.type_of_billing_id,
        });
    }
});

onUnmounted(() => {
    clearInterval(timer);
});

watch(changeBalance, async () => {
    if (changeBalance.value == true) {
        let ids =
            await getClientMainInformationIdAndClientAdditionalInformationId(
                props.id
            );

        await getfieldsEditedWithMultipleModel(
            [
                {
                    client_main_information: "ClientMainInformation",
                },
                {
                    client_additional_information:
                        "ClientAdditionalInformation",
                },
            ],
            props.id,
            {
                client_main_information: ids.clientMainInformationId,
                client_additional_information:
                    ids.clientAdditionalInformationId,
            }
        );
    }
    changeBalance.value = false;
});

const getClientMainInformationIdAndClientAdditionalInformationId = async (
    clientId
) => {
    let res;
    await axios
        .post(
            `/cliente/${clientId}/get-client-main-information-id-and-client-additional-information-id`
        )
        .then((r) => {
            res = r.data;
        });

    return {
        clientMainInformationId: res.clientMainInformationId,
        clientAdditionalInformationId: res.clientAdditionalInformationId,
    };
};

const onSubmit = async () => {
    if (await checkifClientStatusActivadoAndChangeToLocked()) {
        dataForm.data
            .submit("post", `/cliente/${props.action}`, props.action)
            .then((response) => {
                toastr.success("Cliente actualizado correctamente", "Cliente");
            })
            .error((error) => {
                Swal.fire("Error", "Ocurrió un error al actualizar", "error");
            });
    }
};

const checkifClientStatusActivadoAndChangeToLocked = async () => {
    // Verificar si el estado cambió
    if (oldStatus.value == dataForm.data.estado) {
        return true; // No hay cambio de estado, permite continuar
    }

    // Verificar permisos si el estado cambió
    if (!hasPermission.data.canView("client_update_status")) {
        Swal.fire({
            title: "Permiso denegado",
            text: "Solo los administradores pueden cambiar el estado del cliente",
            icon: "error",
            confirmButtonColor: "#3085d6",
            confirmButtonText: "Aceptar",
        });
        return false;
    }

    // Si tiene permisos y el estado cambió, pedir confirmación
    const result = await Swal.fire({
        title: "¿Estás seguro que deseas actualizar el cliente?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, Actualizar",
        cancelButtonText: "Cancelar",
    });

    return result.isConfirmed;
};

const updateThisField = ({ field, value }) => {
    if (field == "type_of_billing_id") {
        emits("getTypeOfBilling", { typeOfBilling: value.value });
    }
    dataForm.data[field] = value;
};

const getCoordenates = ({ lat, lng }) => {
    dataForm.data["geodata"] = `${lat},${lng}`;
};

const textInformation = ref("");
const oldAttributes = ref([]);
const newAttributes = ref([]);
const showInformation = (item) => {
    textInformation.value = item;
    let info = JSON.parse(item.data);
    let dataOld = info.old;
    let dataNew = info.attributes;
    if (dataOld) {
        oldAttributes.value = Object.entries(dataOld).map(([key, value]) => {
            return { [key]: value };
        });
    }

    if (dataNew) {
        newAttributes.value = Object.entries(dataNew).map(([key, value]) => {
            return { [key]: value };
        });
    }
};

function hasDifference(key, value) {
    const newAttribute = newAttributes.value.find((attr) => attr[key]);
    return newAttribute && newAttribute[key] !== value;
}

const onCreatedOnu = (onu) => {
    dataForm.data.gpon_ont = onu.sn;
    currentOnu.value = onu;
    showPanelOnu.value = true;
    showDialogUnconfigured.value = false;
};

const onRemoveOnu = () => {
    dataForm.data.gpon_ont = null;
    showPanelOnu.value = false;
    currentOnu.value = null;
};

const getClient = () => {
    let info = fieldsJson?.value["client_main_information"] ?? null;
    return info
        ? {
              id: props.id,
              name: `${info.name.value} ${info.father_last_name.value} ${info.mother_last_name.value}`,
              address: info.address.value,
          }
        : null;
};

const onFixedShow = async () => {
    if (dataForm.data.gpon_ont) {
        if (!currentOnu.value) {
            loadingOnu.value = true;
            const result = await getOnuByClient(props.id);
            loadingOnu.value = false;
            if (result?.success) {
                currentOnu.value = result.onu;
                showPanelOnu.value = true;
            } else {
                message("Onu no encontrada", "error");
            }
        } else {
            showPanelOnu.value = true;
        }
    } else {
        showDialogUnconfigured.value = true;
        showPanelOnu.value = false;
    }
};

const onUpdateCurrentOnu = (onu) => {
    if (!currentOnu) {
        currentOnu.value = onu;
    } else {
        Object.assign(currentOnu.value, onu);
    }
};
</script>

<style scoped></style>
