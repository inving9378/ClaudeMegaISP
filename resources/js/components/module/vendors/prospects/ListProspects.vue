<template>
    <Crm-Datatable
        module="crm"
        model="Crm"
        list="Lista de Prospectos"
        add="Agregar Crm"
        :persistentFilters="filterSeller"
        :excludeDefaultColumns="['owner_id/datatabletable']"
        :status-badge="statusBadge"
        :overlay-loading="false"
        loading-label="Obteniendo prospectos, por favor espere..."
        no-data-label="Este vendedor no tiene prospectos registrados"
    ></Crm-Datatable>
</template>

<script setup>
import CrmDatatable from "../../crm/CrmDatatable.vue";
import { ref } from "vue";

defineOptions({
    name: "ListProspects",
});

const props = defineProps({
    id: Number,
});

const filterSeller = ref({
    owner_id: [props.id],
});

// Pills de estado del prospecto (opt-in del CrmDatatable; el CRM NO se ve afectado).
// Estados reales de crm_lead_information.crm_status.
const statusBadge = {
    column: "crm_status",
    map: {
        Nuevo: "is-info",
        Contactado: "is-slate",
        Interesado: "is-warn",
        Instalacion: "is-accent",
        Ganado: "is-ok",
        Perdido: "is-bad",
    },
};
</script>
