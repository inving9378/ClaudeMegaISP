<template>
    <div class="q-pa-md">
        <div class="text-h6 q-mb-md">Bandeja de Conciliación</div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            flat bordered
            :loading="loading"
            v-model:pagination="pagination"
            :rows-per-page-options="[15, 30, 50]"
            @request="onRequest"
            no-data-label="No hay reportes pendientes de revisión"
        >
            <template #body-cell-estado="props">
                <q-td :props="props">
                    <q-badge :color="props.value === 'discrepancia' ? 'orange' : 'blue'" :label="props.value" />
                </q-td>
            </template>

            <template #body-cell-monto="props">
                <q-td :props="props">
                    <span :class="props.row.monto_cuadra ? 'text-positive' : 'text-negative text-weight-bold'">
                        ${{ fmt(props.row.monto_reportado) }}
                    </span>
                    <span class="text-grey"> / ${{ fmt(props.row.monto_esperado) }}</span>
                    <q-icon v-if="!props.row.monto_cuadra" name="warning" color="negative" class="q-ml-xs">
                        <q-tooltip>El monto reportado no coincide con el esperado</q-tooltip>
                    </q-icon>
                </q-td>
            </template>

            <template #body-cell-acciones="props">
                <q-td :props="props">
                    <q-btn flat dense color="grey-8" label="Ver" @click="abrirDetalle(props.row)" />
                    <q-btn flat dense color="positive" label="Aprobar" @click="confirmar('aprobar', props.row)" />
                    <q-btn flat dense color="negative" label="Rechazar" @click="confirmar('rechazar', props.row)" />
                </q-td>
            </template>
        </q-table>

        <!-- Detalle -->
        <q-dialog v-model="detalle.show">
            <q-card style="min-width: 460px; max-width: 90vw">
                <q-card-section class="bg-primary text-white row items-center">
                    <div class="text-h6">Reporte #{{ detalle.row.id }}</div>
                    <q-space />
                    <q-badge color="white" text-color="primary" :label="detalle.row.estado" />
                </q-card-section>
                <q-card-section>
                    <div class="row q-col-gutter-sm">
                        <div class="col-12"><b>Cliente:</b> {{ detalle.row.cliente }}</div>
                        <div class="col-6"><b>Monto reportado:</b> ${{ fmt(detalle.row.monto_reportado) }}</div>
                        <div class="col-6"><b>Monto esperado:</b> ${{ fmt(detalle.row.monto_esperado) }}</div>
                        <div class="col-6"><b>Banco emisor:</b> {{ detalle.row.banco_emisor || '—' }}</div>
                        <div class="col-6"><b>Fecha operación:</b> {{ detalle.row.fecha_operacion || '—' }}</div>
                        <div class="col-12"><b>Clave de rastreo:</b> {{ detalle.row.clave_rastreo }}</div>
                        <div class="col-12"><b>Cuenta destino:</b> {{ detalle.row.cuenta || '—' }}</div>
                        <div class="col-12"><b>Referencia:</b> {{ detalle.row.referencia || '—' }}</div>
                    </div>

                    <q-expansion-item class="q-mt-md" label="Resultado CEP (crudo)" dense default-opened>
                        <pre class="cep-json">{{ jsonPretty(detalle.row.cep_resultado) }}</pre>
                    </q-expansion-item>

                    <div class="q-mt-md" v-if="detalle.row.tiene_comprobante">
                        <q-btn color="secondary" outline label="Descargar comprobante"
                               type="a" :href="'/pagos/comprobante/' + detalle.row.id" target="_blank" />
                    </div>
                    <div class="q-mt-sm text-grey text-caption" v-else>Sin comprobante adjunto.</div>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cerrar" v-close-popup />
                    <q-btn color="negative" label="Rechazar" @click="confirmar('rechazar', detalle.row)" />
                    <q-btn color="positive" label="Aprobar" @click="confirmar('aprobar', detalle.row)" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Confirmación -->
        <q-dialog v-model="conf.show">
            <q-card style="min-width: 340px">
                <q-card-section class="text-h6">
                    {{ conf.action === 'aprobar' ? 'Aprobar y conciliar' : 'Rechazar reporte' }}
                </q-card-section>
                <q-card-section class="q-pt-none">
                    <span v-if="conf.action === 'aprobar'">
                        Se registrará el pago y se reactivará el servicio del cliente
                        <b>{{ conf.row.cliente }}</b>. ¿Continuar?
                    </span>
                    <span v-else>
                        El reporte de <b>{{ conf.row.cliente }}</b> se marcará como <b>rechazado</b>. ¿Continuar?
                    </span>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn :color="conf.action === 'aprobar' ? 'positive' : 'negative'"
                           :label="conf.action === 'aprobar' ? 'Sí, aprobar' : 'Sí, rechazar'"
                           :loading="conf.busy" @click="ejecutar" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
export default {
    name: 'PagosConciliacion',
    data() {
        return {
            rows: [],
            loading: false,
            pagination: { page: 1, rowsPerPage: 15, rowsNumber: 0 },
            detalle: { show: false, row: {} },
            conf: { show: false, action: null, row: {}, busy: false },
            columns: [
                { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
                { name: 'monto', label: 'Monto (rep. / esp.)', field: 'monto_reportado', align: 'left' },
                { name: 'banco_emisor', label: 'Banco', field: 'banco_emisor', align: 'left' },
                { name: 'clave_rastreo', label: 'Clave rastreo', field: 'clave_rastreo', align: 'left' },
                { name: 'fecha_operacion', label: 'Fecha op.', field: 'fecha_operacion', align: 'left' },
                { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
                { name: 'creado', label: 'Recibido', field: 'creado', align: 'left' },
                { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'right' },
            ],
        };
    },
    mounted() {
        this.onRequest({ pagination: this.pagination });
    },
    methods: {
        fmt(n) { return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        jsonPretty(v) { try { return JSON.stringify(v, null, 2); } catch (e) { return String(v); } },
        async onRequest(props) {
            const { page, rowsPerPage } = props.pagination;
            this.loading = true;
            try {
                const { data } = await axios.get('/api/pagos/conciliacion', { params: { page, per_page: rowsPerPage } });
                this.rows = data.data;
                this.pagination.page = data.current_page;
                this.pagination.rowsPerPage = rowsPerPage;
                this.pagination.rowsNumber = data.total;
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        abrirDetalle(row) { this.detalle = { show: true, row }; },
        confirmar(action, row) { this.conf = { show: true, action, row, busy: false }; },
        async ejecutar() {
            this.conf.busy = true;
            try {
                const { data } = await axios.post(`/api/pagos/conciliacion/${this.conf.row.id}/${this.conf.action}`);
                this.notify(data.message || 'Listo', 'positive');
                this.conf.show = false;
                this.detalle.show = false;
                this.onRequest({ pagination: this.pagination });
            } catch (e) {
                this.notify(e.response?.data?.message || 'No se pudo procesar', 'negative');
            } finally {
                this.conf.busy = false;
            }
        },
        notify(message, color) {
            if (this.$q && this.$q.notify) this.$q.notify({ message, color });
            else alert(message);
        },
    },
};
</script>

<style scoped>
.cep-json { background: #f6f8fa; border: 1px solid #e3e7ec; border-radius: 6px; padding: .6rem .8rem; font-size: .78rem; max-height: 240px; overflow: auto; white-space: pre-wrap; word-break: break-word; }
[data-layout-mode=dark] .cep-json { background: #252836; border-color: #3a3d50; color: #adb5bd; }
</style>
