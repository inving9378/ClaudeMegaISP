<template>
    <div class="q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="col">
                <div class="text-h5 text-weight-bold">Discrepancias SN — captura manual vs. OLT</div>
                <div class="text-caption text-grey-7">
                    Solo lectura. Compara el número de serie capturado manualmente en la ficha del
                    cliente contra el registrado en la OLT.
                </div>
            </div>
        </div>

        <q-tabs
            v-model="tab"
            dense
            align="left"
            class="text-primary q-mb-md"
            active-color="primary"
            indicator-color="primary"
        >
            <q-tab v-for="cat in categorias" :key="cat.key" :name="cat.key" :label="tabLabel(cat)" />
        </q-tabs>

        <q-separator class="q-mb-md" />

        <template v-for="cat in categorias" :key="cat.key">
            <div v-show="tab === cat.key">
                <div class="row items-center q-mb-sm">
                    <div class="col text-caption text-grey-7">{{ cat.description }}</div>
                    <div class="col-auto">
                        <q-btn
                            color="primary"
                            icon="download"
                            label="Exportar CSV"
                            dense
                            outline
                            :href="`${baseUrl}/${cat.key}/export`"
                            target="_blank"
                        />
                    </div>
                </div>

                <q-table
                    :rows="rows[cat.key]"
                    :columns="cat.columns"
                    :loading="loading[cat.key]"
                    row-key="__row_key"
                    flat
                    bordered
                    no-data-label="Sin discrepancias en esta categoría."
                    rows-per-page-label="Filas por página"
                    v-model:pagination="pagination[cat.key]"
                    @request="(evt) => onRequest(cat.key, evt)"
                />
            </div>
        </template>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'DiscrepanciasSnReport',
    props: {
        baseUrl: { type: String, default: '/red/discrepancias-sn' },
    },
    data() {
        return {
            tab: 'no-normaliza',
            categorias: [
                {
                    key: 'no-normaliza',
                    label: 'No normaliza',
                    description:
                        'El SN capturado manualmente no tiene un formato reconocible (ni 16-hex directo ni el patrón de la OLT).',
                    columns: [
                        { name: 'client_id', label: 'Cliente ID', field: 'client_id', align: 'left' },
                        { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left' },
                        { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
                        { name: 'modem_sn', label: 'SN capturado', field: 'modem_sn', align: 'left' },
                        { name: 'modem_sn_normalizado', label: 'SN normalizado', field: 'modem_sn_normalizado', align: 'left' },
                    ],
                },
                {
                    key: 'difiere-olt',
                    label: 'Normaliza pero difiere de la OLT',
                    description:
                        'El SN capturado normaliza correctamente pero no coincide con el SN real registrado en la OLT del cliente.',
                    columns: [
                        { name: 'client_id', label: 'Cliente ID', field: 'client_id', align: 'left' },
                        { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left' },
                        { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
                        { name: 'modem_sn_normalizado', label: 'SN capturado (norm.)', field: 'modem_sn_normalizado', align: 'left' },
                        { name: 'olt_sn_normalizado', label: 'SN en la OLT (norm.)', field: 'olt_sn_normalizado', align: 'left' },
                    ],
                },
                {
                    key: 'sin-cliente-activo',
                    label: 'Existe en la OLT sin cliente activo',
                    description:
                        'La ONU está asignada a un client_id que no está Activo (o ya no existe) — equipo en la calle sin facturar.',
                    columns: [
                        { name: 'onu_id', label: 'ONU ID', field: 'onu_id', align: 'left' },
                        { name: 'client_id', label: 'Cliente ID', field: 'client_id', align: 'left' },
                        { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left' },
                        { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
                        { name: 'olt_sn', label: 'SN en la OLT', field: 'olt_sn', align: 'left' },
                        { name: 'onu_status', label: 'Estado ONU', field: 'onu_status', align: 'left' },
                    ],
                },
            ],
            rows: { 'no-normaliza': [], 'difiere-olt': [], 'sin-cliente-activo': [] },
            counts: {},
            loading: { 'no-normaliza': false, 'difiere-olt': false, 'sin-cliente-activo': false },
            loaded: { 'no-normaliza': false, 'difiere-olt': false, 'sin-cliente-activo': false },
            pagination: {
                'no-normaliza': { page: 1, rowsPerPage: 25, rowsNumber: 0 },
                'difiere-olt': { page: 1, rowsPerPage: 25, rowsNumber: 0 },
                'sin-cliente-activo': { page: 1, rowsPerPage: 25, rowsNumber: 0 },
            },
        };
    },
    watch: {
        tab(cat) {
            if (!this.loaded[cat]) {
                this.fetch(cat, this.pagination[cat]);
            }
        },
    },
    mounted() {
        this.fetch(this.tab, this.pagination[this.tab]);
    },
    methods: {
        tabLabel(cat) {
            const total = this.counts[cat.key];
            return `${cat.label} (${total === undefined ? '…' : total})`;
        },
        onRequest(cat, requestProp) {
            const { page, rowsPerPage } = requestProp.pagination;
            this.pagination[cat].page = page;
            this.pagination[cat].rowsPerPage = rowsPerPage;
            this.fetch(cat, this.pagination[cat]);
        },
        fetch(cat, pag) {
            this.loading[cat] = true;
            axios
                .get(`${this.baseUrl}/${cat}`, { params: { page: pag.page, per_page: pag.rowsPerPage } })
                .then(({ data }) => {
                    this.rows[cat] = (data.data || []).map((r, idx) => ({
                        ...r,
                        __row_key: `${cat}-${idx}-${r.client_id ?? r.onu_id ?? idx}`,
                    }));
                    this.pagination[cat].rowsNumber = data.total ?? this.rows[cat].length;
                    this.counts = { ...this.counts, [cat]: data.total ?? this.rows[cat].length };
                    this.loaded[cat] = true;
                })
                .catch(() => {
                    if (this.$q && this.$q.notify) {
                        this.$q.notify({
                            message: 'No se pudo cargar el reporte de discrepancias.',
                            color: 'negative',
                            position: 'top',
                        });
                    }
                })
                .finally(() => {
                    this.loading[cat] = false;
                });
        },
    },
};
</script>
