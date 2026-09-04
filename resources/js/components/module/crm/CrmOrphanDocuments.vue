<template>
    <div class="q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="text-h6">Documentos huérfanos</div>
            <q-space />
            <q-input
                v-model="q"
                dense
                outlined
                clearable
                placeholder="Buscar por id de prospecto o título..."
                style="min-width: 280px"
                @keyup.enter="load"
                @clear="load"
            >
                <template #append>
                    <q-icon name="search" class="cursor-pointer" @click="load" />
                </template>
            </q-input>
            <q-btn
                flat
                dense
                icon="download"
                label="Exportar CSV"
                class="q-ml-sm"
                @click="exportCsv"
            />
            <q-btn flat dense icon="refresh" class="q-ml-sm" @click="load" title="Refrescar" />
        </div>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            flat
            bordered
            :loading="loading"
            :pagination="{ rowsPerPage: 25 }"
            no-data-label="Sin documentos huérfanos"
        >
            <template #body-cell-title="props">
                <q-td :props="props">{{ props.row.title || 'Sin título' }}</q-td>
            </template>
            <template #body-cell-created_at="props">
                <q-td :props="props">{{ formatDate(props.row.created_at) }}</q-td>
            </template>
        </q-table>
    </div>
</template>

<script>
export default {
    name: "CrmOrphanDocuments",
    data() {
        return {
            rows: [],
            loading: false,
            q: "",
            columns: [
                { name: "id", label: "ID", field: "id", align: "left", sortable: true },
                { name: "crm_id", label: "Prospecto", field: "crm_id", align: "left", sortable: true },
                { name: "title", label: "Título", field: "title", align: "left", sortable: true },
                { name: "motivo", label: "Motivo", field: "motivo", align: "left", sortable: true },
                { name: "created_at", label: "Creado", field: "created_at", align: "left", sortable: true },
            ],
        };
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get("/crm/documentos-huerfanos/data", {
                    params: { q: this.q || undefined },
                });
                this.rows = data.data || [];
            } catch (e) {
                console.error(e);
                this.notify("No se pudo cargar el listado de documentos huérfanos", "negative");
            } finally {
                this.loading = false;
            }
        },
        exportCsv() {
            const params = this.q ? `?q=${encodeURIComponent(this.q)}` : "";
            window.location.href = `/crm/documentos-huerfanos/csv${params}`;
        },
        formatDate(value) {
            if (!value) return "—";
            return value;
        },
        notify(message, color) {
            if (this.$q && this.$q.notify) this.$q.notify({ message, color });
            else alert(message);
        },
    },
};
</script>
