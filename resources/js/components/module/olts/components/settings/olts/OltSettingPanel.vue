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
                setActiveTab('tab-olt-settings-panel', tab);
            }
        "
    >
        <q-tab name="cards" label="Tarjetas" />
        <q-tab name="pon" label="Puertos PON" />
        <q-tab name="uplink" label="Puertos enlace ascendente" />
        <q-tab name="vlans" label="VLANs" />
    </q-tabs>

    <q-separator />

    <q-tab-panels v-model="tab" animated>
        <q-tab-panel name="cards" style="padding: 0">
            <cards :olt-id="oltId" :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="pon" style="padding: 0">
            <pon-ports :olt-id="oltId" :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="uplink" style="padding: 0">
            <uplink-ports :olt-id="oltId" :has-permission="hasPermission" />
        </q-tab-panel>
        <q-tab-panel name="vlans" style="padding: 0">
            <vlans :olt-id="oltId" :has-permission="hasPermission" />
        </q-tab-panel>
    </q-tab-panels>
</template>

<script setup>
import { ref } from "vue";
import Cards from "./Cards.vue";
import PonPorts from "./PonPorts.vue";
import UplinkPorts from "./UplinkPorts.vue";
import Vlans from "./Vlans.vue";
import { setActiveTab } from "../../../../../../hook/appConfig";

defineOptions({
    name: "OltSettingPanel",
});

defineProps({
    oltId: Number | String,
    hasPermission: Object,
});

const tab = ref(localStorage.getItem("tab-olt-settings-panel") || "cards");
</script>
