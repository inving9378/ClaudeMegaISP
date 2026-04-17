<template>
    <div class="object-field">
        <fieldset>
            <legend>Bono que puede recibir mensualmente</legend>
            <div
                class="row items-center"
                v-for="(b, index) in currentBonus.bonus"
                :key="`bonus-${index}`"
            >
                <div class="col">
                    <label :for="`object-monthly-bonus-${index}`">Bono</label>
                    <q-input
                        v-model.number="b.bonus"
                        dense
                        outlined
                        placeholder="0.00"
                        :for="`object-monthly-bonus-${index}`"
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
                    <label :for="`object-minimum-sales-${index}`"
                        >Ventas necesarias</label
                    >
                    <q-input
                        v-model.number="b.sales"
                        dense
                        outlined
                        placeholder="0"
                        :for="`object-minimum-sales-${index}`"
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
                            @click="currentBonus.bonus.splice(index, 1)"
                            v-else
                        />
                    </div>
                </div>
            </div>
            <div class="object-field">
                <input
                    type="checkbox"
                    v-model="currentBonus.iva"
                    id="iva-monthly-bonus"
                    style="
                        width: 20px;
                        height: 20px;
                        margin-right: 10px;
                        margin-top: 10px;
                    "
                />
                <label
                    for="iva-monthly-bonus"
                    style="position: absolute; margin-top: 10px"
                    >Aplicar descuento IVA a esta comisión</label
                >
            </div>
        </fieldset>
    </div>
</template>

<script setup>
import { onBeforeMount, ref, watch } from "vue";

defineOptions({
    name: "MonthBonus",
});

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    bonus: Object,
});

const emits = defineEmits(["update"]);

const currentBonus = ref();

onBeforeMount(() => {
    currentBonus.value = props.bonus
        ? props.bonus
        : {
              iva: false,
              bonus: [
                  {
                      bonus: 0,
                      sales: 0,
                  },
              ],
          };
});

const newBonus = () => {
    currentBonus.value.bonus.push({
        bonus: 0,
        sales: 0,
    });
};

watch(
    currentBonus,
    (n) => {
        emits("update", props.name, currentBonus.value);
    },
    {
        deep: true,
    }
);
</script>
