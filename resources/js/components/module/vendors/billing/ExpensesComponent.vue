<template>
    <q-dialog
        v-model="showDialog"
        persistent
        @hide="onHide"
        @show="reloadPayments = true"
    >
        <q-card style="width: 1200px; max-width: 130vw">
            <q-card-section>
                <div class="text-h6">Egresos del vendedor</div>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-tabs
                    v-model="tab"
                    dense
                    no-caps
                    class="bg-grey-3 text-grey-7"
                    active-color="primary"
                    indicator-color="primary"
                    align="justify"
                    content-class="no-gutter-x width-auto"
                >
                    <q-tab
                        name="payments"
                        :label="`Pagos ($${
                            Math.round(totalPayment * 100) / 100
                        })`"
                    />
                    <q-tab
                        name="discounts"
                        :label="`Descuentos ($${
                            Math.round(totalDiscount * 100) / 100
                        })`"
                    />
                </q-tabs>
                <q-tab-panels v-model="tab" animated>
                    <q-tab-panel name="payments">
                        <payments-list
                            :user="user"
                            @loaded="(t) => (totalPayment = t)"
                        />
                    </q-tab-panel>
                    <q-tab-panel name="discounts">
                        <discount-list
                            :seller_id="seller_id"
                            @loaded="(t) => (totalDiscount = t)"
                        />
                    </q-tab-panel>
                </q-tab-panels>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Cancelar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import PaymentsList from "./components/invoice/PaymentsList.vue";
import DiscountList from "./components/invoice/DiscountList.vue";
const props = defineProps({
    user: String | Number,
    seller_id: {
        type: Number,
        required: true,
    },
    showModal: {
        type: Boolean,
        required: true,
    },
});

const emits = defineEmits(["hide"]);
const showDialog = ref(false);
const tab = ref("payments");

const totalPayment = ref(0);
const totalDiscount = ref(0);

const onHide = () => {
    emits("hide");
};

watch(
    () => props.showModal,
    (n) => {
        showDialog.value = n;
    }
);
</script>
