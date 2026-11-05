<template>
    <div class="row tc-wrap" :class="{ 'tc-dark': darkMode }">
        <q-tabs
            v-model="currentTab"
            dense
            no-caps
            align="justify"
            :dark="darkMode"
            v-if="tabs"
            @update:model-value="onChangeTab"
        >
            <q-tab
                name="information"
                label="Información"
                :style="{
                    width: `${100 / (allTabs.length + moduleTabsList.length)}%`,
                }"
            />
            <q-tab
                name="documents"
                label="Documentos"
                :style="{
                    width: `${100 / (allTabs.length + moduleTabsList.length)}%`,
                }"
            />
            <q-tab
                v-for="mt in moduleTabsList"
                :key="mt.name"
                :name="mt.name"
                :label="mt.label"
            />
        </q-tabs>
        <q-tab-panels v-model="currentTab" animated :dark="darkMode">
            <q-tab-panel name="information">
                <InformationCrmCrud :action="`update/${id}`" :id="id" />
            </q-tab-panel>

            <q-tab-panel name="documents">
                <DocumentCrmCrud :id="id" :editModal="editModal" />
            </q-tab-panel>

            <q-tab-panel v-for="mt in moduleTabsList" :key="mt.name" :name="mt.name">
                <component :is="mt.component" :id="id" :crm-id="Number(id)" />
            </q-tab-panel>
        </q-tab-panels>
    </div>
</template>

<script setup>
import InformationCrmCrud from "./InformationCrmCrud";
import DocumentCrmCrud from "./document/DocumentCrmCrud";
import { onBeforeMount, onMounted, ref, onUnmounted, getCurrentInstance } from "vue";
import { editModal, showEditModal } from "../../../hook/modalHook";
import { useTabs } from "../../../composables/useTabs";
import { darkMode } from "../../../hook/appConfig";

const ns = `.leak983-crmCrud-${getCurrentInstance().uid}`;
onUnmounted(() => {
    $(document).off(ns);
});

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    tabs: String,
    moduleTabs: {
        type: String,
        default: "[]",
    },
});

const { setLastTab, getLastTab } = useTabs();

const currentTab = ref(null);
const allTabs = ref(JSON.parse(props.tabs) ?? []);

// Pestañas de módulos addon (crm_tab en module.json), ya filtradas por permiso
// en el backend. Solo se montan las cuyo componente está registrado globalmente.
const instance = getCurrentInstance();
const parseModuleTabs = () => {
    let raw = [];
    try {
        raw = JSON.parse(props.moduleTabs || "[]");
    } catch (e) {
        window.console.warn("CrmCrud: moduleTabs JSON inválido", e);
        return [];
    }
    const comps = instance?.appContext?.components || {};
    return raw
        .filter((t) => t.component && comps[t.component])
        .map((t) => ({
            name: `mod-${t.module || t.component}`,
            label: t.label,
            component: t.component,
        }));
};
const moduleTabsList = ref(parseModuleTabs());

onBeforeMount(() => {
    let last = getLastTab("crm", "information");
    console.log(last);

    currentTab.value = last;
});

onMounted(() => {
    $(document).on("click" + ns, ".uil-pen-modal", function () {
        let idItem = $(this).parent().attr("id-item");
        let modal = $(this).parent().attr("toggle-modal");
        showEditModal(idItem, modal);
    });
});

const onChangeTab = (tab) => {
    setLastTab("crm", tab);
};
</script>
