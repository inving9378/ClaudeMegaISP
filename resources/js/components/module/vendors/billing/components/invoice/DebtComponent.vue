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
            (tab) => setActiveTab('activeFactureTabDebts', tab)
        "
    >
        <q-tab name="debts" label="Por cobrar" />
        <q-tab name="discounts" label="Cobradas" />
    </q-tabs>
    <q-tab-panels v-model="tab" animated :dark="darkMode">
        <q-tab-panel
            name="debts"
            style="padding: 5px 2px; --bs-gutter-x: 0px !important"
        >
            <debt-list
                :user="user"
                :seller_id="seller_id"
                :has-edit="hasEdit"
                @loaded="(t) => (totalDbt = t)"
            />
        </q-tab-panel>
        <q-tab-panel
            name="discounts"
            style="padding: 5px 2px; --bs-gutter-x: 0px !important"
        >
            <discount-list
                :seller_id="seller_id"
                @loaded="(t) => (totalDiscount = t)"
            />
        </q-tab-panel>
    </q-tab-panels>
</template>

<script setup>
import { watch, ref, onMounted } from "vue";
import DebtList from "./DebtList.vue";
import DiscountList from "./DiscountList.vue";
import { darkMode, setActiveTab } from "../../../../../../hook/appConfig";

const props = defineProps({
    user: String | Number,
    seller_id: {
        type: Number,
        required: true,
    },
    hasEdit: {
        type: Boolean,
        default: false,
    },
});

const emits = defineEmits(["hide"]);

const tab = ref(localStorage.getItem("activeFactureTabDebts") || "debts");
const totalDbt = ref(0);
const totalDiscount = ref(0);
</script>
