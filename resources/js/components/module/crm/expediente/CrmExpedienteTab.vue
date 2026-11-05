<template>
    <div class="tc-wrap crm-exp-tab" :class="{ 'crm-exp-dark': darkMode }">
        <q-tabs
            v-model="sub"
            dense
            align="left"
            class="crm-exp-subtabs"
            active-color="primary"
            indicator-color="primary"
            mobile-arrows
            outside-arrows
        >
            <q-tab v-for="s in subTabs" :key="s.name" :name="s.name" :label="s.label" no-caps />
        </q-tabs>
        <q-separator />
        <q-tab-panels v-model="sub" animated keep-alive>
            <q-tab-panel v-for="s in subTabs" :key="s.name" :name="s.name">
                <div class="text-subtitle2 q-mb-sm">{{ s.label }}</div>
                <CrmPropuestasPanel v-if="s.name === 'propuestas'" :crm-id="crmId" />
                <div v-else class="text-grey-7">Sin datos todavía para el prospecto {{ crmId }}.</div>
            </q-tab-panel>
        </q-tab-panels>
    </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import CrmPropuestasPanel from "./CrmPropuestasPanel.vue";

const props = defineProps({
    id: { type: [String, Number], default: null },
    crmId: { type: [String, Number], default: null },
});

const subTabs = [
    { name: "general", label: "General" },
    { name: "fiscal", label: "Fiscal" },
    { name: "representacion", label: "Representación legal" },
    { name: "facturacion", label: "Facturación" },
    { name: "ubicaciones", label: "Ubicaciones" },
    { name: "contactos", label: "Contactos" },
    { name: "documentos", label: "Documentos" },
    { name: "propuestas", label: "Propuestas" },
];

const sub = ref("general");
const darkMode = computed(() => document.body.classList.contains("body--dark"));

// Al cambiar de prospecto (navegación SPA) se vuelve a la primera sub-pestaña.
watch(() => props.id, () => { sub.value = "general"; });
</script>

<style scoped>
.crm-exp-tab { width: 100%; }
.crm-exp-dark .text-grey-7 { color: #b0b0b0 !important; }
@media (max-width: 600px) { .crm-exp-tab :deep(.q-tab-panel) { padding: 8px; } }
</style>
