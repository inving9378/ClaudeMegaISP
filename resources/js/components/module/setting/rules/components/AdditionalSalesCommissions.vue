<template>
    <div class="row q-pa-md">
        <div class="col">
            <div class="object-field">
                <label for="object_additional_sales_commission"
                    >Comisión por venta adicional</label
                >
                <q-input
                    v-model="currentBonus.bonus"
                    dense
                    outlined
                    placeholder="0.00"
                    for="object_additional_sales_commission"
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
                    <template v-slot:append>
                        <span>{{ currentBonus.type }}</span>
                    </template>
                    <template #after>
                        <q-select
                            v-model="currentBonus.type"
                            emit-value
                            map-options
                            :options="commissionType"
                            dense
                            options-dense
                            outlined
                            style="width: 100px"
                        />
                    </template>
                </q-input>
            </div>
        </div>
        <div class="col self-center" style="padding-top: 15px">
            <div class="object-field">
                <input
                    type="checkbox"
                    v-model="currentBonus.iva"
                    id="iva-additional-bonus"
                    style="width: 20px; height: 20px; margin-right: 10px"
                />
                <label for="iva-additional-bonus" style="position: absolute"
                    >Aplicar descuento IVA a esta comisión</label
                >
            </div>
        </div>
    </div>
</template>

<script setup>
import { onBeforeMount, ref, watch } from "vue";

defineOptions({
    name: "AdditionalSalesCommissions",
});

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    bonus: Object,
    commissionType: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["update"]);

const currentBonus = ref();

onBeforeMount(() => {
    currentBonus.value = props.bonus
        ? props.bonus
        : {
              iva: false,
              type: "$",
              bonus: 0,
          };
});

watch(
    currentBonus,
    (n) => {
        emits("update", props.name, n);
    },
    {
        deep: true,
    }
);
</script>
