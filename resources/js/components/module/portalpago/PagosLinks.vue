<template>
    <div class="q-pa-md">
        <div class="text-h6 q-mb-md">Ligas de Pago</div>

        <!-- Generar -->
        <q-card flat bordered class="q-mb-lg">
            <q-card-section>
                <div class="text-subtitle1 q-mb-sm">Generar liga</div>
                <div class="row q-col-gutter-md">
                    <div class="col-12 col-md-4">
                        <q-select v-model="cliente" use-input input-debounce="300" :options="clienteOpts"
                                  @filter="filterClientes" @update:model-value="onCliente"
                                  label="Cliente" outlined dense option-label="nombre" clearable
                                  hint="Escribe nombre o # de cliente">
                            <template #no-option><q-item><q-item-section class="text-grey">Sin resultados</q-item-section></q-item></template>
                        </q-select>
                    </div>
                    <div class="col-12 col-md-4">
                        <q-select v-model="factura" :options="facturas" label="Factura pendiente" outlined dense
                                  :disable="!cliente" :loading="loadingFac" clearable
                                  :option-label="f => '#' + f.number + ' — $' + fmt(f.total)" >
                            <template #no-option><q-item><q-item-section class="text-grey">
                                {{ cliente ? 'Este cliente no tiene facturas pendientes' : 'Selecciona un cliente' }}
                            </q-item-section></q-item></template>
                        </q-select>
                    </div>
                    <div class="col-12 col-md-4">
                        <q-select v-model="cuenta" :options="cuentas" label="Cuenta de cobro" outlined dense
                                  :option-label="c => c.nombre + (c.banco ? ' — ' + c.banco : '')" clearable />
                    </div>
                </div>
                <div class="q-mt-md">
                    <q-btn color="primary" label="Generar liga" :disable="!puedeGenerar" :loading="generando" @click="generar" />
                </div>
            </q-card-section>

            <q-card-section v-if="result" class="bg-grey-2">
                <div class="text-subtitle2 q-mb-xs">Liga generada</div>
                <div class="row items-center q-gutter-sm">
                    <q-input :model-value="result.url" readonly outlined dense class="col-12 col-md-7" />
                    <q-btn color="primary" outline label="Copiar URL" @click="copiar(result.url)" />
                    <q-btn color="green" label="Enviar por WhatsApp" type="a" :href="waLink" target="_blank"
                           :disable="!result.cliente_telefono" />
                </div>
                <div class="text-caption text-grey q-mt-xs">
                    Referencia: {{ result.referencia }} · Monto: ${{ fmt(result.monto) }} · Vence: {{ result.expira_at }}
                    <span v-if="!result.cliente_telefono"> · (cliente sin teléfono para WhatsApp)</span>
                </div>
            </q-card-section>
        </q-card>

        <!-- Historial -->
        <q-card flat bordered>
            <q-card-section>
                <div class="row items-center q-col-gutter-sm q-mb-sm">
                    <div class="text-subtitle1 col-grow">Historial de ligas</div>
                    <q-select v-model="filtroEstado" :options="estados" label="Estado" outlined dense clearable
                              style="min-width:160px" @update:model-value="recargar" />
                    <q-input v-model="filtroCliente" label="# Cliente" outlined dense clearable style="max-width:140px"
                             @keyup.enter="recargar" @clear="recargar" />
                    <q-btn color="primary" outline label="Filtrar" @click="recargar" />
                </div>

                <q-table :rows="rows" :columns="columns" row-key="id" flat bordered :loading="loading"
                         v-model:pagination="pagination" :rows-per-page-options="[15, 30, 50]"
                         @request="onRequest" no-data-label="Sin ligas">
                    <template #body-cell-estado="props">
                        <q-td :props="props"><q-badge :color="estadoColor(props.value)" :label="props.value" /></q-td>
                    </template>
                    <template #body-cell-monto="props">
                        <q-td :props="props">${{ fmt(props.value) }}</q-td>
                    </template>
                    <template #body-cell-acciones="props">
                        <q-td :props="props">
                            <q-btn flat dense color="primary" label="Copiar URL" @click="copiar(props.row.url)" />
                            <q-btn flat dense color="green" label="WhatsApp" type="a" :href="waFor(props.row)" target="_blank"
                                   :disable="!props.row.telefono" />
                        </q-td>
                    </template>
                </q-table>
            </q-card-section>
        </q-card>
    </div>
</template>

<script>
export default {
    name: 'PagosLinks',
    props: { cuentas: { type: Array, default: () => [] } },
    data() {
        return {
            cliente: null, clienteOpts: [],
            factura: null, facturas: [], loadingFac: false,
            cuenta: null,
            generando: false, result: null,
            rows: [], loading: false,
            filtroEstado: null, filtroCliente: '',
            estados: ['pendiente', 'reportado', 'validado', 'conciliado', 'expirado', 'rechazado'],
            pagination: { page: 1, rowsPerPage: 15, rowsNumber: 0 },
            columns: [
                { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
                { name: 'document_id', label: 'Factura', field: 'document_id', align: 'left' },
                { name: 'monto', label: 'Monto', field: 'monto', align: 'left' },
                { name: 'referencia', label: 'Referencia', field: 'referencia', align: 'left' },
                { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
                { name: 'creado', label: 'Creada', field: 'creado', align: 'left' },
                { name: 'expira_at', label: 'Vence', field: 'expira_at', align: 'left' },
                { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'right' },
            ],
        };
    },
    computed: {
        puedeGenerar() { return this.cliente && this.factura && this.cuenta; },
        waLink() { return this.result ? this.buildWa(this.result.cliente_telefono, this.result.url, this.result.monto) : '#'; },
    },
    mounted() { this.onRequest({ pagination: this.pagination }); },
    methods: {
        fmt(n) { return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        estadoColor(e) { return { conciliado: 'positive', rechazado: 'negative', discrepancia: 'orange', expirado: 'grey', reportado: 'blue', validado: 'teal', pendiente: 'amber' }[e] || 'grey'; },
        async filterClientes(val, update) {
            if (!val || val.length < 2) { update(() => { this.clienteOpts = []; }); return; }
            try {
                const { data } = await axios.get('/api/pagos/clientes/buscar', { params: { q: val } });
                update(() => { this.clienteOpts = data; });
            } catch (e) { update(() => { this.clienteOpts = []; }); }
        },
        async onCliente(c) {
            this.factura = null; this.facturas = []; this.result = null;
            if (!c) return;
            this.loadingFac = true;
            try { const { data } = await axios.get(`/api/pagos/clientes/${c.client_id}/facturas`); this.facturas = data; }
            catch (e) { console.error(e); }
            finally { this.loadingFac = false; }
        },
        async generar() {
            this.generando = true; this.result = null;
            try {
                const { data } = await axios.post('/api/pagos/links', {
                    client_id: this.cliente.client_id,
                    document_id: this.factura.id,
                    account_id: this.cuenta.id,
                });
                if (data.ok) { this.result = data.link; this.notify('Liga generada', 'positive'); this.onRequest({ pagination: this.pagination }); }
                else this.notify(data.message || 'No se pudo generar', 'negative');
            } catch (e) { this.notify(e.response?.data?.message || 'No se pudo generar', 'negative'); }
            finally { this.generando = false; }
        },
        recargar() { this.pagination.page = 1; this.onRequest({ pagination: this.pagination }); },
        async onRequest(props) {
            const { page, rowsPerPage } = props.pagination;
            this.loading = true;
            try {
                const params = { page, per_page: rowsPerPage };
                if (this.filtroEstado) params.estado = this.filtroEstado;
                if (this.filtroCliente) params.client_id = this.filtroCliente;
                const { data } = await axios.get('/api/pagos/links', { params });
                this.rows = data.data;
                this.pagination.page = data.current_page;
                this.pagination.rowsPerPage = rowsPerPage;
                this.pagination.rowsNumber = data.total;
            } catch (e) { console.error(e); }
            finally { this.loading = false; }
        },
        buildWa(phone, url, monto) {
            const p = String(phone || '').replace(/\D+/g, '');
            const full = p.length === 10 ? '52' + p : p;
            const text = encodeURIComponent(`Hola, aquí está tu liga para pagar tu servicio Meganet por $${this.fmt(monto)}: ${url}`);
            return `https://wa.me/${full}?text=${text}`;
        },
        waFor(row) { return this.buildWa(row.telefono, row.url, row.monto); },
        copiar(text) {
            if (navigator.clipboard) navigator.clipboard.writeText(text).then(() => this.notify('URL copiada', 'positive'));
            else this.notify(text, 'info');
        },
        notify(message, color) { if (this.$q && this.$q.notify) this.$q.notify({ message, color }); else alert(message); },
    },
};
</script>
