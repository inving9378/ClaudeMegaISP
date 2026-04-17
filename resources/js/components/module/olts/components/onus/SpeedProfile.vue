<template>
    <q-table
        bordered
        square
        flat
        :rows="onu?.service_ports ?? []"
        :columns="columns"
        row-key="service_port"
        hide-bottom
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
        ><template v-slot:body-cell-actions="props">
            <q-td class="text-center" style="width: 80px">
                <form-speed-profile
                    :object="props.row"
                    :onu="onu"
                    @update="(data) => emits('update', data)"
                />
            </q-td> </template
    ></q-table>
</template>

<script setup>
import { onMounted, ref } from "vue";
import FormSpeedProfile from "../form/FormSpeedProfile.vue";
defineOptions({
    name: "SpeedProfile",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const columns = ref([
    {
        name: "service_port",
        label: "ID puerto de servicio",
        align: "left",
        field: "service_port",
    },
    {
        name: "svlan",
        align: "left",
        label: "SVLAN",
        field: "svlan",
    },
    {
        name: "cvlan",
        align: "left",
        label: "CVLAN",
        field: "cvlan",
    },
    {
        name: "vlan",
        align: "left",
        label: "VLAN usuario",
        field: "vlan",
    },
    {
        name: "download_speed",
        align: "left",
        label: "Descarga",
        field: "download_speed",
    },
    {
        name: "upload_speed",
        align: "left",
        label: "Subida",
        field: "upload_speed",
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
</script>
