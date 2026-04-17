<template>
    <div class="row">
        <q-tabs
            v-model="currentTab"
            dense
            no-caps
            active-color="indigo-6"
            align="justify"
            v-if="tabs"
            @update:model-value="onChangeTab"
        >
            <q-tab
                name="information"
                label="Información"
                :style="{
                    width: `${100 / allTabs.lenght}%`,
                }"
            />
            <q-tab
                name="documents"
                label="Documentos"
                :style="{
                    width: `${100 / allTabs.lenght}%`,
                }"
            />
        </q-tabs>
        <q-tab-panels v-model="currentTab" animated>
            <q-tab-panel name="information">
                <InformationCrmCrud :action="`update/${id}`" :id="id" />
            </q-tab-panel>

            <q-tab-panel name="documents">
                <DocumentCrmCrud :id="id" :editModal="editModal" />
            </q-tab-panel>
        </q-tab-panels>
    </div>
</template>

<script setup>
import InformationCrmCrud from "./InformationCrmCrud";
import DocumentCrmCrud from "./document/DocumentCrmCrud";
import { onBeforeMount, onMounted, ref } from "vue";
import { editModal, showEditModal } from "../../../hook/modalHook";
import { useTabs } from "../../../composables/useTabs";

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    tabs: String,
});

const { setLastTab, getLastTab } = useTabs();

const currentTab = ref(null);
const allTabs = ref(JSON.parse(props.tabs)) ?? [];

onBeforeMount(() => {
    let last = getLastTab("crm", "information");
    console.log(last);

    currentTab.value = last;
});

onMounted(() => {
    $(document).on("click", ".uil-pen-modal", function () {
        let idItem = $(this).parent().attr("id-item");
        let modal = $(this).parent().attr("toggle-modal");
        showEditModal(idItem, modal);
    });
});

const onChangeTab = (tab) => {
    setLastTab("crm", tab);
};
</script>
