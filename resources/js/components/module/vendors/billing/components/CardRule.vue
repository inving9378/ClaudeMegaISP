<template>
    <div>
        <q-card class="p-3">
            <div class="text-h6 pt-3 px-3">Reglas aplicadas al vendedor</div>

            <div class="row px-3">
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text">Sueldo ($)</span>
                        <div class="form-control">{{ sueldo }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text">Zona</span>
                        <div class="form-control">{{ zona }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text"
                            >Comision por venta ($Monto fijo)</span
                        >
                        <div class="form-control">{{ comisionMontoFijo }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text"
                            >Comision por venta (Porcentaje %)</span
                        >
                        <div class="form-control">{{ comisionPorcentaje }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text"
                            >Número de prospectos</span
                        >
                        <div class="form-control">{{ numeroProspectos }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text">Minimo de ventas</span>
                        <div class="form-control">{{ minimoVentas }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text">Periodo</span>
                        <div class="form-control">{{ periodo }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text">Iva (%)</span>
                        <div class="form-control">{{ iva }}</div>
                    </div>
                </div>
                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text">Bono mensual ($)</span>
                        <div class="form-control">{{ bonoMensual }}</div>
                    </div>
                </div>

                <div class="col-6 mt-2 p-1">
                    <div class="input-group has-validation">
                        <span class="input-group-text"
                            >Comisión fija por venta adicional ($)</span
                        >
                        <div class="form-control">
                            {{ fixed_sales_commission_additional }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-h6 pt-3 px-3" v-show="bonusConditions.length > 0">
                Condiciones para recibir el bono
            </div>

            <div class="row p-3" v-show="bonusConditions.length > 0">
                <div
                    class="col-6 mt-2 p-1"
                    v-for="rule in bonusConditions"
                    :key="rule.name"
                >
                    <div
                        class="input-group has-validation"
                        :class="{
                            'border-start': rule.value === '1000',
                        }"
                    >
                        <span class="input-group-text"
                            >Clientes ({{ rule.number_sales_bonus }})
                        </span>
                        <div class="form-control">{{ rule.amount }}$</div>
                    </div>
                </div>
            </div>
        </q-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { getRuleBySeller } from "../helper/helper";

const props = defineProps({
    seller_id: {
        type: Number,
        required: true,
    },
});

const sueldo = ref(0);
const zona = ref("No aplica");
const comisionMontoFijo = ref(0);
const comisionPorcentaje = ref(0);
const numeroProspectos = ref(0);
const minimoVentas = ref(0);
const periodo = ref("No aplica");
const iva = ref(0);
const bonoMensual = ref(0);
const bonusConditions = ref([]);
const fixed_sales_commission_additional = ref(0);

onMounted(async () => {
    await getRulesOfSeller();
});

const getRulesOfSeller = async () => {
    try {
        const response = await getRuleBySeller(props.seller_id);
        if (!response) {
            return;
        }

        const rul = response;

        sueldo.value = rul.amount || 0;
        zona.value = rul.zone || "No aplica";
        comisionMontoFijo.value = rul.fixed_sales_commission || 0;
        comisionPorcentaje.value = rul.commission_percentage || 0;
        numeroProspectos.value = rul.number_of_prospects || 0;
        minimoVentas.value = rul.minimum_sales || 0;
        periodo.value = rul.period || "No aplica";
        iva.value = rul.iva || 0;
        bonoMensual.value = rul.total_bonus || 0;
        fixed_sales_commission_additional.value =
            rul.fixed_sales_commission_additional;

        bonusConditions.value = JSON.parse(rul.conditions)
            .filter((condition) => condition.amount > 0)
            .sort((a, b) => b.number_sales_bonus - a.number_sales_bonus);
    } catch (error) {
        console.log(error);
        // Reset fields in case of error
        sueldo.value = 0;
        zona.value = "No aplica";
        comisionMontoFijo.value = 0;
        comisionPorcentaje.value = 0;
        numeroProspectos.value = 0;
        minimoVentas.value = 0;
        periodo.value = "No aplica";
        iva.value = 0;
        bonoMensual.value = 0;
        bonusConditions.value = [];
    }
};
</script>

<style scoped>
.bg-light {
    background-color: #f8f9fa !important;
}
.text-danger {
    color: #dc3545 !important;
}
</style>
