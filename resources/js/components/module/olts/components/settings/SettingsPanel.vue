<template>
    <q-tabs
        v-model="tab"
        dense
        no-caps
        inline-label
        class="bg-grey-3 text-grey-7"
        active-color="primary"
        indicator-color="primary"
        align="justify"
        content-class="no-gutter-x width-auto"
        @update:model-value="
            (tab) => {
                setActiveTab('tab-olt-settings', tab);
            }
        "
    >
        <q-tab name="zones" label="Zonas" />
        <q-tab name="odbs" label="ODBs" />
        <q-tab name="types" label="Tipos de ONU" />
        <q-tab name="profiles" label="Perfiles de velocidad" />
        <q-tab name="olts" label="OLTs" />
        <q-tab name="billings" label="Subscripciones" />
    </q-tabs>

    <q-separator />

    <q-tab-panels v-model="tab" animated>
        <q-tab-panel name="zones" style="padding: 0">
            <zones :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="odbs" style="padding: 0">
            <odbs :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="types" style="padding: 0">
            <type-onus :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="profiles" style="padding: 0">
            <profiles :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="olts" style="padding: 0">
            <olts :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="billings" style="padding: 0">
            <billings :has-permission="hasPermission" />
        </q-tab-panel>
    </q-tab-panels>
</template>

<script setup>
import { ref } from "vue";
import Zones from "./Zones.vue";
import Odbs from "./Odbs.vue";
import TypeOnus from "./TypeOnus.vue";
import Profiles from "./Profiles.vue";
import Olts from "./Olts.vue";
import Billings from "./Billings.vue";
import { setActiveTab } from "../../../../../hook/appConfig";

defineOptions({
    name: "SettingsPanel",
});

defineProps({
    hasPermission: Object,
});

const tab = ref(localStorage.getItem("tab-olt-settings") || "zones");
</script>
