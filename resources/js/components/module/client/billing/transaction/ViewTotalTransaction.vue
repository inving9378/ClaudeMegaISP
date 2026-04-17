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
                            <div id="transaction-total" data-colors='["#777aca", "#5156be", "#a8aada"]' class="apex-charts"></div>
                        </div>
                        <div class="col-sm align-self-center">
                            <div class="mt-4 mt-sm-0">
                                <div>
                                    <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-success"></i> Total</p>
                                    <h6>{{ transactions.total }} $</h6>
                                </div>

                                <div class="mt-4 pt-2">
                                    <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-primary"></i> Credito</p>
                                    <h6>{{ transactions.credit || 0 }} $</h6>
                                </div>

                                <div class="mt-4 pt-2">
                                    <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-info"></i> Debito</p>
                                    <h6>{{ transactions.debit || 0 }} $</h6>
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
    name: "ViewTotalTransaction",
    props: {
        idClient: String,
        transactions: Object
    },
    setup(props){
        const char = ref(null);

        watch(
            () => props.transactions,
            (transactions, transactionsBefore) => {
                initComponent(transactions);
            }
        );

        const initComponent = (transactions) => {
            if (_.values(transactions).length) {
                let series = [transactions.debit, transactions.credit];
                let labels = ['debit','credit'];

                if (char.value) char.value.destroy();
                char.value = apexchartInit('transaction-total',series, labels)
            }
        }

        onMounted(() => {
            initComponent(props.transactions);
        })
        return {}
    }
}
</script>

<style scoped>

</style>
