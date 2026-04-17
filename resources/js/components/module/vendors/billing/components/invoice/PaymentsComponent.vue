<template>
    <q-tabs
        :dark="darkMode"
        v-model="tab"
        dense
        no-caps
        :class="!darkMode ? 'bg-grey-3 text-grey-7' : null"
        active-color="primary"
        indicator-color="primary"
        align="justify"
        content-class="no-gutter-x width-auto"
        @update:model-value="
            (tab) => setActiveTab('activeFactureTabPayments', tab)
        "
    >
        <q-tab name="pending" label="Por cobrar" />
        <q-tab name="completed" label="Cobrados" />
    </q-tabs>
    <q-tab-panels v-model="tab" animated :dark="darkMode">
        <q-tab-panel
            name="pending"
            style="padding: 5px 2px; --bs-gutter-x: 0px !important"
        >
            <pending-payments :seller="seller_id" :has-edit="hasEdit" />
        </q-tab-panel>
        <q-tab-panel
            name="completed"
            style="padding: 5px 2px; --bs-gutter-x: 0px !important"
        >
            <payments-completed :user="user" />
        </q-tab-panel>
    </q-tab-panels>
</template>

<script setup>
import { watch, ref, onMounted } from "vue";
import PaymentsCompleted from "./PaymentsCompleted.vue";
import PendingPayments from "./PendingPayments.vue";
import { darkMode, setActiveTab } from "../../../../../../hook/appConfig";

const props = defineProps({
    user: String | Number,
    seller_id: {
        type: Number,
        required: true,
    },
    hasEdit: Boolean,
});

const emits = defineEmits(["hide"]);

const tab = ref(localStorage.getItem("activeFactureTabPayments") || "pending");
</script>
