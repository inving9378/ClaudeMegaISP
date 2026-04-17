<template>
    <q-btn
        color="primary"
        class="q-mr-sm"
        label="Añadir gasto"
        no-caps
        @click="showModal = true"
        v-if="!object"
    />

    <q-btn
        flat
        round
        dense
        color="primary"
        icon="edit"
        size="12px"
        @click="showModal = true"
        v-else
    />

    <modal
        :show="showModal"
        size="lg"
        @update:show="showModal = $event"
        :title="`${object ? 'Editar' : 'Registrar'} gasto/proveedor`"
    >
        <template #body>
            <q-card flat style="margin: -15px">
                <q-card-section style="max-height: 60vh" class="scroll">
                    <q-form ref="formRef" greedy>
                        <div class="row text-left">
                            <div
                                class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6"
                            >
                                <label for="paymentMethod"
                                    >Método de pago</label
                                >
                                <q-select
                                    v-model="formData.payment_method_id"
                                    outlined
                                    for="paymentMethod"
                                    dense
                                    options-dense
                                    option-label="type"
                                    option-value="id"
                                    emit-value
                                    :clearable="true"
                                    map-options
                                    :options="paymentMethods"
                                    :rules="[(val) => !!val || 'Requerido']"
                                    :dark="darkMode"
                                >
                                    <template v-slot:selected-item="scope">
                                        <q-item-label
                                            lines="1"
                                            style="margin-top: 5px"
                                            >{{ scope.opt.type }}</q-item-label
                                        >
                                    </template>
                                </q-select>

                                <label for="paymentDate">Fecha de pago</label>
                                <VueDatePicker
                                    id="paymentDate"
                                    v-model="formData.payment_date"
                                    position="right"
                                    locale="es"
                                    :teleport="true"
                                    week-start="0"
                                    :format="customFormat"
                                    :enableTimePicker="false"
                                    :dark="darkMode"
                                >
                                </VueDatePicker>

                                <label for="invoiceNumber" class="q-mt-md"
                                    >Número de recibo</label
                                >
                                <q-input
                                    for="invoiceNumber"
                                    outlined
                                    dense
                                    v-model="formData.invoice_number"
                                    :rules="[(val) => !!val || 'Requerido']"
                                    :dark="darkMode"
                                >
                                    <template #after>
                                        <q-btn
                                            icon="fas fa-magic"
                                            color="primary"
                                            padding="8px"
                                            square
                                            @click="
                                                () =>
                                                    (formData.invoice_number =
                                                        newReceiptNumber())
                                            "
                                        />
                                    </template>
                                </q-input>

                                <label for="amount">Cantidad</label>
                                <q-input
                                    for="amount"
                                    outlined
                                    dense
                                    v-model.number="formData.amount"
                                    :rules="[
                                        (val) =>
                                            !isNaN(parseFloat(val)) ||
                                            'Requerido',
                                        (val) =>
                                            val > 0 ||
                                            'Ingrese una cantidad mayor que 0',
                                    ]"
                                    :dark="darkMode"
                                />
                            </div>
                            <div
                                class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6"
                            >
                                <label for="comments" class="form-label"
                                    >Comentarios</label
                                >
                                <textarea
                                    v-model="formData.comments"
                                    class="form-control"
                                    rows="10"
                                ></textarea>
                            </div>
                        </div>
                    </q-form>
                </q-card-section>
            </q-card>
        </template>
        <template #footer>
            <q-btn color="primary" label="Guardar" no-caps @click="save" />
        </template>
        <template #loading>
            <q-inner-loading
                :showing="loading"
                label="Guardando datos, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
            />
        </template>
    </modal>
</template>

<script setup>
import { ref, watch } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import { useDatePicker } from "../../../../../../composables/useDatePicker";
import Modal from "../../../../../../shared/ModalSimple.vue";
import { darkMode } from "../../../../../../hook/appConfig";
import {
    error500,
    errorValidation,
    message,
} from "../../../../../../helpers/toastMsg";
import {
    storeSuppliersExpenses,
    updateSuppliersExpenses,
} from "../../helper/cutSuppliersExpenses";
import { useUtils } from "../../../../../../composables/useUtils";

const props = defineProps({
    object: Object,
    boxId: Number,
    paymentMethods: {
        type: Array,
        default: [],
    },
});

const { customFormat } = useDatePicker();
const { newReceiptNumber } = useUtils();

const emits = defineEmits(["created", "updated"]);

const showModal = ref(false);
const formRef = ref(null);
const formData = ref({
    payment_method_id: null,
    payment_date: null,
    comments: null,
    invoice_number: null,
    amount: 0,
});
const loading = ref(false);

watch(showModal, (n) => {
    if (n) {
        formData.value = props.object
            ? { ...props.object }
            : {
                  payment_method_id:
                      props.paymentMethods.length > 0
                          ? props.paymentMethods[0].id
                          : null,
                  payment_date: moment(),
                  comments: null,
                  invoice_number: newReceiptNumber(),
                  amount: 0,
              };
    }
});

watch(
    () => props.paymentMethods,
    (n) => {
        if (n.length > 0 && formData.value.payment_method_id === null) {
            formData.value.payment_method_id = n[0].id;
        }
    }
);

const save = () => {
    formRef.value.validate().then((success) => {
        if (success) {
            if (props.object) {
                update();
            } else {
                store();
            }
        } else {
            errorValidation();
        }
    });
};

const store = async () => {
    loading.value = true;
    const result = await storeSuppliersExpenses({
        ...formData.value,
        box_id: props.boxId,
    });
    if (result) {
        emits("created", result);
        showModal.value = false;
        message("Gasto adicionado correctamente");
    } else {
        error500();
    }
    loading.value = false;
};

const update = async () => {
    loading.value = true;
    const result = await updateSuppliersExpenses(props.object.id, {
        ...formData.value,
    });
    if (result) {
        emits("updated", result);
        showModal.value = false;
        message("Gasto modificado correctamente");
    } else {
        error500();
    }
    loading.value = false;
};
</script>
