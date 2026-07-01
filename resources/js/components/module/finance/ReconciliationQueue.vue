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
            @row-click="(evt, row) => openDetail(row)"
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
            <template #body-cell-resolucion="props">
                <q-td :props="props">
                    <template v-if="props.row.status !== 'open'">
                        <div class="text-body2">{{ props.row.resolution_note || '—' }}</div>
                        <div class="text-caption text-grey" v-if="props.row.resolved_by_name">
                            por {{ props.row.resolved_by_name }}<span v-if="props.row.resolved_at"> · {{ props.row.resolved_at }}</span>
                        </div>
                    </template>
                    <span v-else class="text-grey">—</span>
                </q-td>
            </template>
            <template #body-cell-acciones="props">
                <q-td :props="props">
                    <q-btn dense flat color="primary" icon="visibility" :label="props.row.status === 'open' && canResolve ? 'Revisar' : 'Ver'"
                           @click.stop="openDetail(props.row)" />
                </q-td>
            </template>
        </q-table>

        <!-- Modal de detalle + resolución -->
        <q-dialog v-model="modal.show">
            <q-card style="min-width: 480px; max-width: 92vw">
                <q-card-section class="row items-center bg-grey-2">
                    <div class="text-h6">Ticket de conciliación #{{ modal.row?.id }}</div>
                    <q-space />
                    <q-badge v-if="modal.row" :color="statusColor(modal.row.status)" :label="statusLabel(modal.row.status)" />
                </q-card-section>

                <q-card-section v-if="modal.row" class="q-gutter-y-sm">
                    <div class="row"><div class="col-4 text-grey-8">Cliente</div><div class="col-8">{{ modal.row.client_name || '—' }}</div></div>
                    <div class="row"><div class="col-4 text-grey-8">Referencia MEG</div><div class="col-8">{{ modal.row.client_reference || '—' }}</div></div>
                    <div class="row"><div class="col-4 text-grey-8">Monto</div><div class="col-8">{{ modal.row.amount != null ? money(modal.row.amount) : '—' }}</div></div>
                    <div class="row"><div class="col-4 text-grey-8">Razón</div><div class="col-8"><q-badge outline color="deep-orange" :label="modal.row.reason_label" /></div></div>
                    <div class="row"><div class="col-4 text-grey-8">Detalle</div><div class="col-8">{{ modal.row.detail || '—' }}</div></div>
                    <div class="row"><div class="col-4 text-grey-8">Fecha</div><div class="col-8">{{ modal.row.created_at || '—' }}</div></div>

                    <q-separator class="q-my-sm" />

                    <!-- Ticket abierto: capturar nota antes de cerrar -->
                    <template v-if="modal.row.status === 'open' && canResolve">
                        <q-input
                            v-model="modal.note"
                            type="textarea"
                            autogrow
                            outlined
                            label="Nota de resolución *"
                            hint="Explica cómo se resolvió o por qué se descarta. Obligatorio."
                            :error="!!modal.err"
                            :error-message="modal.err"
                            counter
                            maxlength="1000"
                        />
                    </template>

                    <!-- Ticket ya cerrado: rastro de resolución -->
                    <template v-else-if="modal.row.status !== 'open'">
                        <div class="row"><div class="col-4 text-grey-8">Resolución</div><div class="col-8">{{ modal.row.resolution_note || '—' }}</div></div>
                        <div class="row"><div class="col-4 text-grey-8">Resuelto por</div><div class="col-8">{{ modal.row.resolved_by_name || '—' }}<span v-if="modal.row.resolved_at"> · {{ modal.row.resolved_at }}</span></div></div>
                    </template>

                    <div v-else class="text-grey">No tienes permiso para resolver este ticket.</div>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup :disable="modal.busy" />
                    <template v-if="modal.row && modal.row.status === 'open' && canResolve">
                        <q-btn color="grey-8" label="Descartar" :loading="modal.busy"
                               :disable="!noteValid" @click="confirm('descartar')" />
                        <q-btn color="positive" label="Resolver" :loading="modal.busy"
                               :disable="!noteValid" @click="confirm('resolver')" />
                    </template>
                </q-card-actions>
            </q-card>
        </q-dialog>
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
            openCount: 0,
            status: 'open',
            modal: { show: false, row: null, note: '', err: '', busy: false },
            columns: [
                { name: 'cliente', label: 'Cliente', field: 'client_name', align: 'left' },
                { name: 'amount', label: 'Monto', field: 'amount', align: 'right' },
                { name: 'reason', label: 'Razón', field: 'reason_label', align: 'left' },
                { name: 'detail', label: 'Detalle', field: 'detail', align: 'left' },
                { name: 'created_at', label: 'Fecha', field: 'created_at', align: 'left' },
                { name: 'status', label: 'Estado', field: 'status', align: 'center' },
                { name: 'resolucion', label: 'Resolución', field: 'resolution_note', align: 'left' },
                { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'right' },
            ],
        };
    },
    computed: {
        noteValid() {
            return (this.modal.note || '').trim().length >= 3;
        },
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
        openDetail(row) {
            this.modal = { show: true, row, note: '', err: '', busy: false };
        },
        async confirm(action) {
            if (!this.noteValid) {
                this.modal.err = 'Escribe una nota explicando cómo se resolvió.';
                return;
            }
            this.modal.busy = true;
            this.modal.err = '';
            try {
                const { data } = await axios.post(
                    `/finanzas/conciliacion/${this.modal.row.id}/${action}`,
                    { resolution_note: this.modal.note.trim() }
                );
                this.notify(data.message, 'positive');
                this.openCount = data.open ?? this.openCount;
                this.modal.show = false;
                this.load();
            } catch (e) {
                if (e.response?.status === 422) {
                    this.modal.err = e.response.data.errors?.resolution_note?.[0]
                        || e.response.data.message || 'Revisa la nota.';
                } else {
                    this.notify(e.response?.data?.message || 'No se pudo procesar', 'negative');
                }
            } finally { this.modal.busy = false; }
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
