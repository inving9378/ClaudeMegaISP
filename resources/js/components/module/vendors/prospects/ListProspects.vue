<template>
    <Crm-Datatable
        module="crm"
        model="Crm"
        list="Lista de Prospectos"
        add="Agregar Crm"
        :persistentFilters="filterSeller"
        :excludeDefaultColumns="['owner_id/datatabletable']"
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
</script>
