<template>
    <div class="q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="text-h6">Portal de Pago</div>
            <q-space />
            <div class="text-subtitle2 text-grey">{{ kpis.mes }}</div>
        </div>

        <div class="row q-col-gutter-md">
            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section class="text-center">
                        <i class="bi bi-graph-up-arrow" style="font-size:26px;color:#0057A8"></i>
                        <div class="text-h4 text-weight-bold text-primary">{{ kpis.pct_auto_conciliado }}%</div>
                        <div class="text-subtitle2">Ligas auto-conciliadas este mes</div>
                        <div class="text-caption text-grey">{{ kpis.auto_conciliadas }} de {{ kpis.total_ligas_mes }}</div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section class="text-center">
                        <i class="bi bi-inbox-fill" style="font-size:26px;color:#b25e00"></i>
                        <div class="text-h4 text-weight-bold text-warning">{{ kpis.pendientes_bandeja }}</div>
                        <div class="text-subtitle2">Reportes en bandeja</div>
                        <div class="text-caption text-grey">pendiente_validacion + discrepancia</div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section class="text-center">
                        <i class="bi bi-cash-coin" style="font-size:26px;color:#07703a"></i>
                        <div class="text-h4 text-weight-bold text-positive">{{ montoFmt }}</div>
                        <div class="text-subtitle2">Monto conciliado este mes</div>
                        <div class="text-caption text-grey">&nbsp;</div>
                    </q-card-section>
                </q-card>
            </div>
        </div>

        <div class="q-mt-lg row q-gutter-sm">
            <q-btn color="primary" outline label="Ir a Conciliación" @click="go('/pagos/conciliacion')" />
            <q-btn color="primary" outline label="Cuentas de Cobro" @click="go('/pagos/cuentas')" />
            <q-btn color="primary" outline label="Ligas de Pago" @click="go('/pagos/links')" />
        </div>

        <q-inner-loading :showing="loading"><q-spinner size="40px" color="primary" /></q-inner-loading>
    </div>
</template>

<script>
export default {
    name: 'PagosDashboard',
    data() {
        return {
            loading: false,
            kpis: {
                pct_auto_conciliado: 0,
                auto_conciliadas: 0,
                total_ligas_mes: 0,
                pendientes_bandeja: 0,
                monto_conciliado_mes: 0,
                mes: '',
            },
        };
    },
    computed: {
        montoFmt() {
            return '$' + Number(this.kpis.monto_conciliado_mes || 0)
                .toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        go(url) { window.location.href = url; },
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/pagos/kpis');
                this.kpis = data;
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
