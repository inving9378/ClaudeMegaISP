<template>
    <q-btn
        icon="fas fa-list"
        flat
        size="xs"
        round
        color="primary"
        @click="showDialog = true"
    >
        <q-tooltip>Detalles</q-tooltip>
    </q-btn>

    <q-dialog
        v-model="showDialog"
        persistent
        full-width
        @show="onTabChange('cards')"
    >
        <q-card>
            <q-item>
                <q-item-section>
                    <q-item-label class="text-h6">
                        Detalles de la OLT "{{ object.name }}"
                    </q-item-label>
                </q-item-section>
                <q-item-section avatar>
                    <q-btn
                        flat
                        round
                        icon="close"
                        @click="showDialog = false"
                    />
                </q-item-section>
            </q-item>
            <q-separator />
            <q-card-section class="q-pt-none">
                <q-tabs
                    v-model="tab"
                    dense
                    align="justify"
                    :breakpoint="0"
                    :indicator-color="darkMode ? null : 'primary'"
                    :active-color="darkMode ? null : 'primary'"
                    @update:model-value="() => onTabChange(tab)"
                >
                    <q-tab
                        v-for="t in Object.keys(tabConfig)"
                        :key="t"
                        :name="t"
                        :label="tabConfig[t].label"
                        no-caps
                    />
                </q-tabs>
                <q-tab-panels v-model="tab" :dark="darkMode" animated>
                    <q-tab-panel name="cards" class="q-pa-none">
                        <olt-cards
                            :object="tabConfig.cards"
                            @reload="
                                (force) => onTabChange('cards', true, force)
                            "
                            @update-columns="
                                (columns) => onUpdateColumns('cards', columns)
                            "
                        />
                    </q-tab-panel>
                    <q-tab-panel name="ponPorts" class="q-pa-none">
                        <olt-pon-ports
                            :object="tabConfig.ponPorts"
                            @reload="
                                (force) => onTabChange('ponPorts', true, force)
                            "
                            @update-columns="
                                (columns) =>
                                    onUpdateColumns('ponPorts', columns)
                            "
                        />
                    </q-tab-panel>
                    <q-tab-panel name="interruptionsPon" class="q-pa-none">
                        <olt-interruption-pon
                            :object="tabConfig.interruptionsPon"
                            @reload="
                                (force) =>
                                    onTabChange('interruptionsPon', true, force)
                            "
                            @update-columns="
                                (columns) =>
                                    onUpdateColumns('interruptionsPon', columns)
                            "
                        />
                    </q-tab-panel>
                    <q-tab-panel name="uplinkPorts" class="q-pa-none">
                        <olt-up-link-port-details
                            :object="tabConfig.uplinkPorts"
                            @reload="
                                (force) =>
                                    onTabChange('uplinkPorts', true, force)
                            "
                            @update-columns="
                                (columns) =>
                                    onUpdateColumns('uplinkPorts', columns)
                            "
                        />
                    </q-tab-panel>
                    <q-tab-panel name="vlans" class="q-pa-none">
                        <olt-vlans
                            :object="tabConfig.vlans"
                            @reload="
                                (force) => onTabChange('vlans', true, force)
                            "
                            @update-columns="
                                (columns) => onUpdateColumns('vlans', columns)
                            "
                        />
                    </q-tab-panel>
                    <q-tab-panel name="onus" class="q-pa-none">
                        <olt-onus
                            :object="tabConfig.onus"
                            @reload="
                                (force) => onTabChange('onus', true, force)
                            "
                            @update-columns="
                                (columns) => onUpdateColumns('onus', columns)
                            "
                            @created="
                                (row) => {
                                    tabConfig.onus.rows.push(row);
                                }
                            "
                            @enabled="onEnabled"
                            @removed="
                                (id) => {
                                    tabConfig.onus.rows =
                                        tabConfig.onus.rows.filter(
                                            (r) => r.id !== id
                                        );
                                }
                            "
                        />
                    </q-tab-panel>
                    <q-tab-panel name="unconfiguredOnus" class="q-pa-none">
                        <olt-unconfigured-onus
                            :object="tabConfig.unconfiguredOnus"
                            @reload="
                                (force) =>
                                    onTabChange('unconfiguredOnus', true, force)
                            "
                            @update-columns="
                                (columns) =>
                                    onUpdateColumns('unconfiguredOnus', columns)
                            "
                        />
                    </q-tab-panel>
                </q-tab-panels>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    flat
                    no-caps
                    label="Cerrar"
                    @click="showDialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref } from "vue";
import OltCards from "./OltCards.vue";
import OltPonPorts from "./OltPonPorts.vue";
import OltInterruptionPon from "./OltInterruptionPon.vue";
import OltUpLinkPortDetails from "./OltUpLinkPortDetails.vue";
import OltVlans from "./OltVlans.vue";
import OltUnconfiguredOnus from "./OltUnconfiguredOnus.vue";
import OltOnus from "./OltOnus.vue";
import { darkMode } from "../../../../hook/appConfig";
import { getOLTData } from "../helper/request";
import { useDataTable } from "../../../../composables/useDataTable";
import { message } from "../../../../helpers/toastMsg";

defineOptions({
    name: "OltCards",
});

const props = defineProps({
    object: Object,
});

const { getColumns } = useDataTable();

const showDialog = ref(false);
const tab = ref("cards");
const tabConfig = ref({
    cards: {
        name: "cards",
        label: "Tarjetas",
        columns: [],
        rows: [],
        loading: false,
        loaded: false,
        olt: props.object,
    },
    ponPorts: {
        name: "pon-ports",
        label: "Puertos PON",
        columns: [],
        rows: [],
        loading: false,
        loaded: false,
        olt: props.object,
    },
    interruptionsPon: {
        name: "outage-pons",
        label: "Interrupciones PON",
        columns: [],
        rows: [],
        loading: false,
        loaded: false,
        olt: props.object,
    },
    uplinkPorts: {
        name: "uplink-ports",
        label: "Puertos enlace ascendente",
        columns: [],
        rows: [],
        loading: false,
        loaded: false,
        olt: props.object,
    },
    vlans: {
        name: "vlans",
        label: "VLANs",
        columns: [],
        rows: [],
        loading: false,
        loaded: false,
        olt: props.object,
    },
    onus: {
        name: "onus",
        label: "ONUs",
        columns: [],
        rows: [],
        loading: false,
        loaded: false,
        olt: props.object,
    },
    unconfiguredOnus: {
        name: "unconfigured-onus",
        label: "ONUs desconfiguradas",
        columns: [],
        rows: [],
        loading: false,
        loaded: false,
        olt: props.object,
    },
});

const onTabChange = async (t, reload = false, force = false) => {
    const tb = tabConfig.value[t];
    tb.loading = true;
    if (!tb.loaded) {
        const columns = await getColumns(`olt-${tb.name}`);
        tb.columns = columns ?? [];
    }
    if (!tb.loaded || reload) {
        let result = await getOLTData(`olt-${tb.name}/${props.object.id}`, {
            force,
        });
        if (result.success) {
            tb.rows = result.rows;
            if (result.message) {
                message(
                    `Se han mostrados los datos locales dado que ocurrió un error con la api smart olt. El error devuelto es: ${result.message} `,
                    "error"
                );
            }
        }
    }
    tb.loading = false;
    tb.loaded = true;
};

const onUpdateColumns = (tab, columns) => {
    tabConfig.value[tab].columns = columns;
};

const onEnabled = (id, enable) => {
    const row = tabConfig.value.onus.rows.find((r) => r.id === id);
    if (row) {
        row.administrative_status = enable ? "Enabled" : "Disabled";
    }
};
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
