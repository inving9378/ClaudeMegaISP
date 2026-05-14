<template>
    <div class="megafamilia-licencias">
        <div class="card mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title m-0">Licencias</h5>
                    <div>
                        <input
                            v-if="hasSearch"
                            v-model="search"
                            class="form-control form-control-sm d-inline-block"
                            style="width: 220px;"
                            placeholder="Buscar…"
                            @keyup.enter="fetch"
                        />
                        <button class="btn btn-sm btn-outline-secondary ms-2" @click="fetch" :disabled="loading">
                            <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                        </button>
                    </div>
                </div>

                <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
                <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
                <div v-else-if="rows.length === 0" class="text-muted">Sin datos todavía.</div>
                <div v-else>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th v-for="col in columns" :key="col">{{ col }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.id || row['_idx']">
                                    <td v-for="col in columns" :key="col" v-text="renderCell(row, col)"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="pagination" class="text-muted small mt-2">
                        Página {{ pagination.current_page }} / {{ pagination.last_page }} ·
                        {{ pagination.total }} registros
                    </div>
                </div>

                <div class="text-muted small mt-3">
                    <i class="fa fa-tools"></i>
                    Pendiente: refinar UI con patrón <code>cards-and-table</code>.
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaLicencias',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            rows: [],
            columns: [],
            pagination: null,
            search: '',
            loading: false,
            errorMsg: '',
        };
    },
    computed: {
        hasSearch() { return true; },
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true;
            this.errorMsg = '';
            try {
                const params = {};
                if (this.search) params.search = this.search;
                const { data } = await axios.get(`${this.baseUrl}/licencias/data`, { params });
                this.normalize(data);
            } catch (e) {
                this.errorMsg = e.response?.data?.error || e.message;
            } finally {
                this.loading = false;
            }
        },
        normalize(data) {
            let rows = null;
            if (Array.isArray(data)) {
                rows = data;
            } else if (data && Array.isArray(data.data)) {
                rows = data.data;
                this.pagination = {
                    current_page: data.current_page,
                    last_page: data.last_page,
                    total: data.total,
                };
            } else if (data && typeof data === 'object') {
                const arrKey = Object.keys(data).find((k) => Array.isArray(data[k]));
                rows = arrKey ? data[arrKey] : [];
            } else {
                rows = [];
            }
            rows = (rows || []).map((r, i) => ({ ...r, _idx: i }));
            this.rows = rows;
            this.columns = rows.length > 0
                ? Object.keys(rows[0]).filter((k) => k !== '_idx').slice(0, 6)
                : [];
        },
        renderCell(row, col) {
            const v = row[col];
            if (v === null || v === undefined) return '';
            if (typeof v === 'object') return JSON.stringify(v);
            return String(v);
        },
    },
};
</script>
