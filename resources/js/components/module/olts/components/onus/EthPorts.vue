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
        ><template v-slot:body-cell-admin_state="props">
            <q-td>
                <q-badge
                    color="negative"
                    v-if="props.row.admin_state === 'Disabled'"
                    >Puerto apagado</q-badge
                >
                <span v-else>{{ props.row.admin_state }}</span>
            </q-td> </template
        ><template v-slot:body-cell-actions="props">
            <q-td class="text-center" style="width: 80px">
                <form-eth-port
                    :object="props.row"
                    :onu="onu"
                    @update="(data) => emits('update', data)"
                />
            </q-td> </template
    ></q-table>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import FormEthPort from "../form/FormEthPort.vue";
defineOptions({
    name: "EthPorts",
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
                vlan,
                allowed_vlan;
            switch (val) {
                case "Access":
                    vlan = row.vlan;
                    if (vlan !== null && vlan.trim() !== "") {
                        txt = `${txt} VLAN: ${vlan}`;
                    }
                    break;
                case "Trunk":
                    allowed_vlan = row.allowed_vlans;
                    if (allowed_vlan !== null && allowed_vlan.trim() !== "") {
                        txt = `${txt} VLANs: ${allowed_vlan}`;
                    }
                    break;
                case "Hybrid":
                    vlan = row.vlan;
                    if (vlan !== null && vlan.trim() !== "") {
                        txt = `${txt}: Def-VLAN ${vlan}`;
                    }
                    allowed_vlan = row.allowed_vlans;
                    if (allowed_vlan !== null && allowed_vlan.trim() !== "") {
                        txt = `${txt} + TAG ${allowed_vlan}`;
                    }
                    break;
                default:
                    break;
            }
            return txt;
        },
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
    return props.onu.ethernet_ports;
});
</script>
