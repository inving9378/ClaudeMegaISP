<template>
    <div class="row">
        <div class="col-xl-5 m-auto">
            <!-- card -->
            <div class="card card-h-100">
                <!-- card body -->
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center mb-4">
                        <h5 class="card-title me-2">Totales</h5>
                    </div>

                    <div class="row align-items-center">
                        <div class="col-sm">
                            <div id="payment-total" data-colors='["#777aca", "#5156be", "#a8aada"]' class="apex-charts"></div>
                        </div>
                        <div class="col-sm align-self-center">
                            <div class="mt-4 mt-sm-0">
                                <div>
                                    <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-success"></i> Total</p>
                                    <h6>{{ totals }} $</h6>
                                </div>

                                <div class="mt-4 pt-2">
                                    <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-primary"></i> Efectivo en Caja</p>
                                    <h6>{{ groups['Efectivo en Caja'] || 0 }} $</h6>
                                </div>

                                <div class="mt-4 pt-2">
                                    <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-info"></i> Transferencia Bancaria</p>
                                    <h6>{{ groups['Transferencia Bancaria'] || 0 }} $</h6>
                                </div>

                                <div class="mt-4 pt-2">
                                    <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-secondary"></i> Pago a Tecnico</p>
                                    <h6>{{ groups['Pago a Tecnico'] || 0 }} $</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end card -->
        </div>
    </div>
</template>

<script>
import {onMounted, ref, watch} from "vue";
import {apexchartInit} from "../helpers/apexchart.init";

export default {
    name: "ViewTotalPayment",
    props: {
        idClient: String,
        payments: Object
    },
    setup(props){
        const totals = ref(0);
        const groups = ref([]);
        const char = ref(null);

        watch(
            () => props.payments,
            (payments, paymentsBefore) => {
                initComponent(payments);
            }
        );

        const initComponent = (payments) => {
            if (_.values(payments).length) {
                let series = [];
                let labels = [];
                let total = 0;
                _.forEach(payments, (v) => {
                    series.push(v.value);
                    total += v.value;

                    labels.push(v.type);
                    groups.value[v.type] = v.value;
                });
                if (char.value) char.value.destroy();
                char.value = apexchartInit('payment-total',series, labels)
                totals.value = total;
            }
        }

        onMounted(() => {
            initComponent(props.payments);
        })
        return {
            totals,
            groups
        }
    }
}
</script>

<style scoped>

</style>
