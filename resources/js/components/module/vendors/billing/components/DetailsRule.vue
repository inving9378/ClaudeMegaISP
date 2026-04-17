<template>
    <div>
        <q-card class="p-3">
            <div class="text-h6 pt-3 px-3">Detalles del pago</div>

            <ul class="list-group mb-3 p-3">
                <li
                    class="list-group-item d-flex justify-content-between lh-sm"
                >
                    <div>
                        <h6 class="my-0">Sueldo</h6>
                        <small class="text-muted">Monto fijo al mes</small>
                    </div>
                    <span class="text-muted">$ {{ sueldo }}</span>
                </li>

                <li
                    class="list-group-item d-flex justify-content-between lh-sm"
                >
                    <div>
                        <h6 class="my-0">Comision</h6>
                        <small class="text-muted">Total de comisiones</small>
                    </div>
                    <span class="text-muted">$ {{ comision }}</span>
                </li>

                <li
                    class="list-group-item d-flex justify-content-between lh-sm"
                >
                    <div>
                        <h6 class="my-0">Bono</h6>
                        <small class="text-muted">
                            Por haber alcanzado {{ ventas }} ventas
                        </small>
                    </div>
                    <span class="text-muted">$ {{ bono }}</span>
                </li>

                <li class="list-group-item d-flex justify-content-between">
                    <span>Total</span>
                    <strong>$ {{ getPaymentTotal }}</strong>
                </li>
            </ul>
        </q-card>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";

const sueldo = ref(0);
const comision = ref(0);
const bono = ref(0);
const ventas = ref(0);

const getPaymentTotal = computed(() => {
    return (
        parseFloat(sueldo.value) +
        parseFloat(comision.value) +
        parseFloat(bono.value)
    ).toFixed(2);
});
</script>

<style scoped>
.bg-light {
    background-color: #f8f9fa !important;
}
.text-danger {
    color: #dc3545 !important;
}
</style>
