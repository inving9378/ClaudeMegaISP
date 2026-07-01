<template>
    <div class="q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="text-h6">Cola de Conciliación</div>
            <q-badge v-if="openCount" color="warning" class="q-ml-sm" :label="`${openCount} abiertos`" />
            <q-space />
            <q-btn-toggle
                v-model="status"
                dense flat
                toggle-color="primary"
                :options="[
                    { label: 'Abiertos', value: 'open' },
                    { label: 'Resueltos', value: 'resolved' },
                    { label: 'Descartados', value: 'dismissed' },
                    { label: 'Todos', value: 'all' },
                ]"
                @update:model-value="load"
            />
            <q-btn flat dense icon="refresh" class="q-ml-sm" @click="load" title="Refrescar" />
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            flat bordered
            :loading="loading"
            :pagination="{ rowsPerPage: 25 }"
            no-data-label="No hay pagos/conciliaciones en esta vista."
        >
            <template #body-cell-cliente="props">
                <q-td :props="props">
                    <div>{{ props.row.client_name || '—' }}</div>
                    <div class="text-caption text-grey" v-if="props.row.client_reference">
                        {{ props.row.client_reference }}
                    </div>
                </q-td>
            </template>
            <template #body-cell-amount="props">
                <q-td :props="props">{{ props.row.amount != null ? money(props.row.amount) : '—' }}</q-td>
            </template>
            <template #body-cell-reason="props">
                <q-td :props="props">
                    <q-badge outline color="deep-orange" :label="props.row.reason_label" />
                </q-td>
            </template>
            <template #body-cell-status="props">
                <q-td :props="props">
                    <q-badge :color="statusColor(props.row.status)" :label="statusLabel(props.row.status)" />
                </q-td>
            </template>
            <template #body-cell-acciones="props">
                <q-td :props="props">
                    <template v-if="props.row.status === 'open' && canResolve">
                        <q-btn dense flat color="positive" label="Resolver"
                               :loading="busy === props.row.id" @click="act(props.row, 'resolver')" />
                        <q-btn dense flat color="grey-8" label="Descartar"
                               :loading="busy === props.row.id" @click="act(props.row, 'descartar')" />
                    </template>
                    <span v-else class="text-grey">—</span>
                </q-td>
            </template>
        </q-table>
    </div>
</template>

<script>
export default {
    name: 'ReconciliationQueue',
    props: {
        canResolve: { type: Boolean, default: false },
    },
    data() {
        return {
            rows: [],
            loading: false,
            busy: null,
            openCount: 0,
            status: 'open',
            columns: [
                { name: 'cliente', label: 'Cliente', field: 'client_name', align: 'left' },
                { name: 'amount', label: 'Monto', field: 'amount', align: 'right' },
                { name: 'reason', label: 'Razón', field: 'reason_label', align: 'left' },
                { name: 'detail', label: 'Detalle', field: 'detail', align: 'left' },
                { name: 'created_at', label: 'Fecha', field: 'created_at', align: 'left' },
                { name: 'status', label: 'Estado', field: 'status', align: 'center' },
                { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'right' },
            ],
        };
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/finanzas/conciliacion/list', { params: { status: this.status } });
                this.rows = data.items || [];
                this.openCount = data.open || 0;
            } catch (e) { console.error(e); this.notify('No se pudo cargar la cola', 'negative'); }
            finally { this.loading = false; }
        },
        async act(row, action) {
            this.busy = row.id;
            try {
                const { data } = await axios.post(`/finanzas/conciliacion/${row.id}/${action}`);
                this.notify(data.message, 'positive');
                this.openCount = data.open ?? this.openCount;
                this.load();
            } catch (e) {
                this.notify(e.response?.data?.message || 'No se pudo procesar', 'negative');
            } finally { this.busy = null; }
        },
        money(v) {
            return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v);
        },
        statusLabel(s) {
            return { open: 'Abierto', resolved: 'Resuelto', dismissed: 'Descartado' }[s] || s;
        },
        statusColor(s) {
            return { open: 'warning', resolved: 'positive', dismissed: 'grey' }[s] || 'grey';
        },
        notify(message, color) {
            if (this.$q && this.$q.notify) this.$q.notify({ message, color });
            else alert(message);
        },
    },
};
</script>
