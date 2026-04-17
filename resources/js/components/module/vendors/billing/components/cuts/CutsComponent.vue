<template>
    <div class="row no-padding">
        <q-tabs
            v-model="activeTab"
            dense
            no-caps
            inline-label
            align="justify"
            :dark="darkMode"
            content-class="no-gutter-x"
            :class="!darkMode ? 'bg-grey-3 text-grey-7' : null"
            active-color="primary"
            indicator-color="primary"
            style="flex-shrink: inherit !important"
            @update:model-value="
                (tab) => setActiveTab('activeBillingCutsTab', tab)
            "
        >
            <q-tab label="Lista de cortes" name="tab-billing-list-cuts" />
            <q-tab label="Corte mostrador" name="tab-gbilling-cuts" />
        </q-tabs>
        <q-tab-panels
            v-model="activeTab"
            animated
            :dark="darkMode"
            style="padding: 0px !important; min-height: 200px"
        >
            <q-tab-panel
                name="tab-billing-list-cuts"
                style="padding: 5px 2px; --bs-gutter-x: 0px !important"
            >
                <list-cuts :user-id="userId" @show-box="showBox" />
            </q-tab-panel>
            <q-tab-panel
                name="tab-gbilling-cuts"
                style="padding: 5px 2px; --bs-gutter-x: 0px !important"
            >
                <template v-if="box && Object.hasOwn(box, 'id')">
                    <general-box-info
                        :box="box"
                        :different="box.id !== boxToDay.id"
                        @change-actual-box="box = boxToDay"
                    />

                    <br />

                    <payments-received-component
                        :user-id="userId"
                        :box="box"
                        @loaded="(val) => (totalReceived = val)"
                    />

                    <br />

                    <technical-instalations-and-services-component
                        :has-permission="hasPermission"
                        :box="box"
                        :sucursal-id="sucursalId"
                        :closing="closingBox"
                        @loaded="(val) => (totalInstallation = val)"
                    />

                    <br />

                    <suppliers-expenses-component
                        :has-permission="hasPermission"
                        :box="box"
                        :closing="closingBox"
                        @loaded="(val) => (totalSuppliersExpenses = val)"
                    />

                    <br />

                    <extra-income-component
                        :has-permission="hasPermission"
                        :box="box"
                        :closing="closingBox"
                        @loaded="(val) => (totalExtraIncome = val)"
                    />

                    <br />

                    <div class="row no-gutter-x">
                        <div
                            class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6"
                        >
                            <q-list dense>
                                <q-item>
                                    <q-item-section
                                        avatar
                                        style="min-width: 160px !important"
                                    >
                                        Total de pagos recibidos:
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <q-field
                                            outlined
                                            dense
                                            style="width: 140px"
                                            :dark="darkMode"
                                        >
                                            <template v-slot:control>
                                                <div
                                                    class="self-center full-width no-outline"
                                                    tabindex="0"
                                                >
                                                    {{ totalReceived }}
                                                </div>
                                            </template>
                                        </q-field>
                                    </q-item-section>
                                </q-item>
                                <q-item>
                                    <q-item-section
                                        avatar
                                        style="min-width: 160px !important"
                                    >
                                        Total extras:
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <q-field
                                            outlined
                                            dense
                                            style="width: 140px"
                                            :dark="darkMode"
                                        >
                                            <template v-slot:control>
                                                <div
                                                    class="self-center full-width no-outline"
                                                    tabindex="0"
                                                >
                                                    {{ totalExtraIncome }}
                                                </div>
                                            </template>
                                        </q-field>
                                    </q-item-section>
                                </q-item>
                                <q-item>
                                    <q-item-section
                                        avatar
                                        style="min-width: 160px !important"
                                    >
                                        Total técnicos:
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <q-field
                                            outlined
                                            dense
                                            style="width: 140px"
                                            :dark="darkMode"
                                        >
                                            <template v-slot:control>
                                                <div
                                                    class="self-center full-width no-outline"
                                                    tabindex="0"
                                                >
                                                    {{ totalInstallation }}
                                                </div>
                                            </template>
                                        </q-field>
                                    </q-item-section>
                                </q-item>
                                <q-item>
                                    <q-item-section
                                        avatar
                                        style="min-width: 160px !important"
                                    >
                                        Total proveedores:
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <q-field
                                            outlined
                                            dense
                                            style="width: 140px"
                                            :dark="darkMode"
                                        >
                                            <template v-slot:control>
                                                <div
                                                    class="self-center full-width no-outline"
                                                    tabindex="0"
                                                >
                                                    {{ totalSuppliersExpenses }}
                                                </div>
                                            </template>
                                        </q-field>
                                    </q-item-section>
                                </q-item>
                                <q-item>
                                    <q-item-section
                                        avatar
                                        style="min-width: 160px !important"
                                    >
                                        Total neto:
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <q-field
                                            outlined
                                            dense
                                            style="width: 140px"
                                            :dark="darkMode"
                                        >
                                            <template v-slot:control>
                                                <div
                                                    class="self-center full-width no-outline"
                                                    tabindex="0"
                                                >
                                                    {{ totalNet }}
                                                </div>
                                            </template>
                                        </q-field>
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </div>
                        <div
                            class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6"
                        >
                            <observations-component
                                :has-permission="hasPermission"
                                :box="box"
                                :closing="closingBox"
                            />
                        </div>
                    </div>
                    <div class="text-right q-mt-lg" v-if="!box.closed">
                        <q-btn
                            label="Cerrar caja"
                            color="primary"
                            no-caps
                            :loading="closingBox"
                            @click="confirmClose"
                        />
                    </div>
                </template>
                <p
                    class="text-center q-mt-xl"
                    v-else-if="(!box || !Object.hasOwn(box, 'id')) && !loading"
                >
                    No existe caja asociada a este mostrador para hoy
                </p>
                <q-inner-loading
                    :showing="loading"
                    label="Procesando, por favor espere..."
                    label-class="text-primary"
                    label-style="font-size: 1.1em"
                />
            </q-tab-panel>
        </q-tab-panels>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { darkMode, setActiveTab } from "../../../../../../hook/appConfig.js";
import ListCuts from "./ListCuts.vue";
import GeneralBoxInfo from "./GeneralBoxInfo.vue";
import PaymentsReceivedComponent from "./PaymentsReceivedComponent.vue";
import TechnicalInstalationsAndServicesComponent from "./TechnicalInstalationsAndServicesComponent.vue";
import SuppliersExpensesComponent from "./SuppliersExpensesComponent.vue";
import ExtraIncomeComponent from "./ExtraIncomeComponent.vue";
import ObservationsComponent from "./ObservationsComponent.vue";
import { close, findBox, getCurrentBox } from "../../helper/cutBox.js";
import Swal from "sweetalert2";
import { hideLoading, showLoading } from "../../../../../../helpers/loading.js";
import { message } from "../../../../../../helpers/toastMsg.js";

const props = defineProps({
    userId: Number,
    sellerId: Number,
    sucursalId: Number,
    hasPermission: Object,
    isCounter: Boolean,
});

const activeTab = ref(
    localStorage.getItem("activeBillingCutsTab") || "tab-billing-list-cuts"
);

const totalExtraIncome = ref(0);
const totalSuppliersExpenses = ref(0);
const totalReceived = ref(0);
const totalInstallation = ref(0);
const loading = ref(false);
const closingBox = ref(false);
const box = ref(null);
let boxToDay = null;

onMounted(async () => {
    showLoading();
    const result = await getCurrentBox(props.userId);
    hideLoading();
    if (result) {
        boxToDay = result;
        box.value = result;
    }
});

const totalNet = computed(() => {
    return (
        totalExtraIncome.value +
        totalReceived.value +
        totalInstallation.value -
        totalSuppliersExpenses.value
    );
});

const showBox = async (b) => {
    showLoading();
    const result = await findBox(b);
    hideLoading();
    if (result) {
        box.value = result;
        activeTab.value = "tab-gbilling-cuts";
    }
};

const confirmClose = async () => {
    Swal.fire({
        title: "Confirmación!",
        text: "Esta seguro que desea cerrar su caja, de ser asi no podra modificar, asegurese de asignar las instalaciones y servicios técnicos a un responsable",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Cerrar caja",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            closingBox.value = true;
            const result = await close(box.value.id);
            closingBox.value = false;
            if (result) {
                box.value = result;
                successClose(result.id);
            } else {
                message("Error al cerrar la caja", "error");
            }
        }
    });
};

const successClose = (id) => {
    Swal.fire({
        title: "Info!",
        text: "Caja cerrada correctamente, desea ver los detalles de esta?",
        icon: "info",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Si",
        cancelButtonText: "No",
    }).then(async (result) => {
        if (result.isConfirmed) {
            window.open(`/sellers/cuts/box-pdf/${id}`, "_blank");
        }
    });
};
</script>
