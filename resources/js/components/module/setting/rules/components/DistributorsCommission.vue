<template>
    <div class="object-field">
        <fieldset>
            <legend>Comisión de los distribuidores</legend>
            <div class="row">
                <div class="col">
                    <label for="initial-commission"
                        >Comisión inicial por venta</label
                    >
                    <q-input
                        v-model.number="currentCommission.initial"
                        dense
                        outlined
                        placeholder="0.00"
                        for="initial-commission"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        prefix="$"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    />
                </div>
                <div class="col q-mx-sm">
                    <label for="sales-commission">Ventas mínimas</label>
                    <q-input
                        v-model.number="currentCommission.sales"
                        dense
                        outlined
                        placeholder="0"
                        for="sales-commission"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    >
                    </q-input>
                </div>
            </div>
            <div
                class="row items-center"
                v-for="(b, index) in currentCommission.bonus"
                :key="`distributors-commission-bonus-${index}`"
            >
                <div class="col">
                    <label :for="`object-total-commission-${index}`"
                        >Comisión</label
                    >
                    <q-input
                        v-model.number="b.commission"
                        dense
                        outlined
                        placeholder="0.00"
                        :for="`object-total-commission-${index}`"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        suffix="%"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    >
                    </q-input>
                </div>
                <div class="col q-mx-sm">
                    <label :for="`object-contract-${index}`">Contrato de</label>
                    <q-select
                        v-model="b.contract"
                        emit-value
                        map-options
                        :options="contractsDurations"
                        :for="`object-contract-${index}`"
                        clearable
                        dense
                        options-dense
                        outlined
                        hint="Requerido"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
                <div class="col-3 items-center">
                    <div class="column">
                        <q-btn
                            label="Añadir condiciones"
                            no-caps
                            color="indigo-7"
                            style="margin-top: 6px"
                            @click="newBonus"
                            v-if="index === 0"
                        />
                        <q-btn
                            label="Eliminar"
                            no-caps
                            color="danger"
                            style="margin-top: 6px"
                            @click="currentCommission.bonus.splice(index, 1)"
                            v-else
                        />
                    </div>
                </div>
            </div>
            <div class="object-field">
                <input
                    type="checkbox"
                    v-model="currentCommission.iva"
                    id="iva-distributors-commisions"
                    style="
                        width: 20px;
                        height: 20px;
                        margin-right: 10px;
                        margin-top: 10px;
                    "
                />
                <label
                    for="iva-distributors-commisions"
                    style="position: absolute; margin-top: 10px"
                    >Aplicar descuento IVA a esta comisión</label
                >
            </div>
        </fieldset>
    </div>
</template>

<script setup>
import { onBeforeMount, onMounted, ref, watch } from "vue";

defineOptions({
    name: "DistributorsCommission",
});

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    commission: Object,
    contractsDurations: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["update"]);

const currentCommission = ref();

onBeforeMount(() => {
    currentCommission.value = props.commission
        ? props.commission
        : {
              initial: 0,
              sales: 0,
              iva: false,
              bonus: [
                  {
                      commission: 0,
                      contract: null,
                  },
              ],
          };
});

const newBonus = () => {
    currentCommission.value.bonus.push({
        commission: 0,
        contract: null,
    });
};

watch(
    currentCommission,
    (n) => {
        emits("update", props.name, n);
    },
    {
        deep: true,
    }
);
</script>
