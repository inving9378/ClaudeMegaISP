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
                    color="primary"
                    v-if="props.row.admin_state === 'Enabled'"
                    >Habilitado</q-badge
                >
            </q-td> </template
        ><template v-slot:body-cell-config-action="props">
            <q-td class="text-center" style="width: 120px">
                <form-vo-ip-port
                    :object="props.row"
                    :onu="onu"
                    @update="(data) => emits('update', data)"
                />
            </q-td> </template
        ><template v-slot:body-cell-disable-action="props">
            <q-td class="text-center" style="width: 120px">
                <q-btn
                    color="danger"
                    size="sm"
                    no-caps
                    :loading="loading && props.row.port === current_port"
                    @click="disablePort(props.row)"
                    v-if="props.row.admin_state === 'Enabled'"
                    ><q-icon name="mdi-minus" style="padding: 0px !important" />
                    Deshabilitar
                </q-btn>
            </q-td>
        </template></q-table
    >
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import FormVoIpPort from "../form/FormVoIpPort.vue";
import axios from "axios";
import { message } from "../../../../../helpers/toastMsg";

defineOptions({
    name: "VoipPorts",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const columns = ref([
    {
        name: "sip_profile",
        align: "left",
        label: "Perfil SIP",
        field: "sip_profile",
    },
    {
        name: "port",
        label: "Puerto",
        align: "left",
        field: "port",
    },
    {
        name: "phone_number",
        align: "left",
        label: "Teléfono",
        field: "phone_number",
    },
    {
        name: "admin_state",
        align: "left",
        label: "Estado admin",
        field: "admin_state",
    },
]);

const loading = ref(false);
const current_port = ref(null);

onMounted(() => {
    if (props.hasPermission?.data.canView("onu_edit")) {
        columns.value.push({
            name: "config-action",
            align: "center",
            label: "",
            field: "config-action",
            style: "width: 80px",
        });
        columns.value.push({
            name: "disable-action",
            align: "center",
            label: "",
            field: "disable-action",
            style: "width: 80px",
        });
    }
});

const data = computed(() => {
    let ports = props.onu.voip_ports ?? [];
    if (ports.length === 2) {
        return ports;
    } else if (ports.length === 0) {
        return [
            {
                sip_profile: null,
                port: "pots_0/1",
                phone_number: null,
                admin_state: null,
            },
            {
                sip_profile: null,
                port: "pots_0/2",
                phone_number: null,
                admin_state: null,
            },
        ];
    }
    let exists = ports.find((p) => p.port === "pots_0/1"),
        not_configured = {
            sip_profile: null,
            port: exists ? "pots_0/2" : "pots_0/1",
            phone_number: null,
            admin_state: null,
        };
    if (exists) {
        ports.push(not_configured);
    } else {
        ports.splice(0, 0, not_configured);
    }
    return ports;
});

const disablePort = async (port) => {
    loading.value = true;
    current_port.value = port.port;
    await axios
        .post(`/olts/onus/set-onu-voip-port/${props.onu.id}`, {
            status: "disable",
            attr_to_server: {
                voip_port: port.port,
            },
        })
        .then((res) => {
            let response = res.data;
            if (!response.success) {
                message(response.error ?? response.message, "error");
            } else {
                emits("update", response.onu);
                message(`Puerto deshabilitado correctamente`, "success");
            }
        })
        .catch((err) => {
            message("Ha ocurrido un error al procesar la solicitud", "error");
        })
        .finally(() => {
            loading.value = false;
            current_port.value = null;
        });
};
</script>
