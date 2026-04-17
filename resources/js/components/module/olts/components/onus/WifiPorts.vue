<template>
    <q-table
        bordered
        square
        flat
        :rows="data"
        :columns="columns"
        row-key="port"
        hide-bottom
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
        ><template v-slot:body-cell-actions="props">
            <q-td class="text-center" style="width: 80px">
                <form-wifi-port
                    :object="props.row"
                    :onu="onu"
                    @update="(data) => emits('update', data)"
                />
            </q-td>
        </template>
    </q-table>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import FormWifiPort from "../form/FormWifiPort.vue";
defineOptions({
    name: "WifiPorts",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const columns = ref([
    {
        name: "port",
        label: "Puerto",
        align: "left",
        field: "port",
    },
    {
        name: "admin_state",
        align: "left",
        label: "Estado admin",
        field: "admin_state",
    },
    {
        name: "mode",
        align: "left",
        label: "Modo",
        field: "mode",
        format: (val, row) => {
            let txt = val,
                vlan;
            switch (val) {
                case "Access":
                    vlan = row.vlan;
                    if (vlan !== null && vlan.trim() !== "") {
                        txt = `${txt} VLAN: ${vlan}`;
                    }
                    break;
                default:
                    break;
            }
            return txt;
        },
    },
    {
        name: "ssid",
        align: "left",
        label: "SSID",
        field: "ssid",
    },
    {
        name: "dhcp",
        align: "left",
        label: "DHCP",
        field: "dhcp",
    },
]);

onMounted(() => {
    if (props.hasPermission?.data.canView("onu_edit")) {
        columns.value.push({
            name: "actions",
            align: "center",
            label: "",
            field: "actions",
            style: "width: 80px",
        });
    }
});

const data = computed(() => {
    return props.onu.wifi_ports;
});
</script>
