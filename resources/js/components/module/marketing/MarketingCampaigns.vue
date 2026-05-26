<template>
    <div>
        <!-- Filtros -->
        <div class="row q-gutter-sm q-mb-md">
            <q-input v-model="filters.search" label="Buscar" outlined dense clearable class="col-12 col-sm-4" @update:model-value="loadCampaigns">
                <template #append><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="filters.status" :options="statusOptions" label="Estado" outlined dense clearable class="col-12 col-sm-3"
                emit-value map-options @update:model-value="loadCampaigns" />
            <q-select v-model="filters.channel" :options="['whatsapp','facebook','instagram']" label="Canal" outlined dense clearable class="col-12 col-sm-2"
                @update:model-value="loadCampaigns" />
        </div>

        <!-- Tabla -->
        <q-table
            :rows="campaigns"
            :columns="columns"
            row-key="id"
            flat
            bordered
            :loading="loading"
            :pagination="pagination"
            @request="onRequest"
        >
            <template #body-cell-status="props">
                <q-td :props="props">
                    <q-badge :color="statusColor(props.value)" :label="statusLabel(props.value)" />
                </q-td>
            </template>
            <template #body-cell-channel="props">
                <q-td :props="props">
                    <q-badge
                        v-for="ch in (props.value || [])"
                        :key="ch"
                        :color="channelColor(ch)"
                        :label="ch"
                        class="q-mr-xs"
                    />
                </q-td>
            </template>
            <template #body-cell-actions="props">
                <q-td :props="props">
                    <q-btn flat dense icon="visibility" color="primary" @click="$emit('campaign-selected', props.row.id)">
                        <q-tooltip>Ver campaña</q-tooltip>
                    </q-btn>
                    <q-btn v-if="props.row.status === 'draft'" flat dense icon="send" color="orange"
                        @click="submitApproval(props.row.id)">
                        <q-tooltip>Enviar a aprobación</q-tooltip>
                    </q-btn>
                    <q-btn v-if="props.row.status === 'pending_approval'" flat dense icon="check_circle" color="positive"
                        @click="approveCampaign(props.row.id)">
                        <q-tooltip>Aprobar</q-tooltip>
                    </q-btn>
                    <q-btn v-if="props.row.status === 'approved'" flat dense icon="play_arrow" color="positive"
                        @click="activateCampaign(props.row.id)">
                        <q-tooltip>Activar</q-tooltip>
                    </q-btn>
                    <q-btn v-if="props.row.status === 'active'" flat dense icon="pause" color="warning"
                        @click="pauseCampaign(props.row.id)">
                        <q-tooltip>Pausar</q-tooltip>
                    </q-btn>
                    <q-btn v-if="['draft','pending_approval'].includes(props.row.status)" flat dense icon="delete" color="negative"
                        @click="deleteCampaign(props.row.id)">
                        <q-tooltip>Eliminar</q-tooltip>
                    </q-btn>
                </q-td>
            </template>
        </q-table>
    </div>
</template>

<script>
export default {
    name: 'MarketingCampaigns',
    emits: ['campaign-selected', 'refresh-stats'],
    data() {
        return {
            campaigns: [],
            loading: false,
            filters: { search: '', status: null, channel: null },
            pagination: { page: 1, rowsPerPage: 15, rowsNumber: 0 },
            statusOptions: [
                { label: 'Borrador', value: 'draft' },
                { label: 'Pendiente aprobación', value: 'pending_approval' },
                { label: 'Aprobada', value: 'approved' },
                { label: 'Activa', value: 'active' },
                { label: 'Pausada', value: 'paused' },
                { label: 'Terminada', value: 'finished' },
            ],
            columns: [
                { name: 'title', label: 'Campaña', field: 'title', align: 'left', sortable: true },
                { name: 'status', label: 'Estado', field: 'status', align: 'center' },
                { name: 'channel', label: 'Canal', field: 'channel', align: 'center' },
                { name: 'target_zone', label: 'Zona', field: 'target_zone', align: 'left' },
                { name: 'start_date', label: 'Inicio', field: 'start_date', align: 'center', sortable: true },
                { name: 'end_date', label: 'Fin', field: 'end_date', align: 'center' },
                { name: 'contents_count', label: 'Contenidos', field: 'contents_count', align: 'center' },
                { name: 'leads_count', label: 'Leads', field: 'leads_count', align: 'center' },
                { name: 'actions', label: 'Acciones', field: 'actions', align: 'center' },
            ],
        };
    },
    mounted() {
        this.loadCampaigns();
    },
    methods: {
        async loadCampaigns(props) {
            this.loading = true;
            const page = props?.pagination?.page || this.pagination.page;
            try {
                const { data } = await axios.post('/marketing/campaigns/table', {
                    ...this.filters,
                    page,
                });
                this.campaigns        = data.data;
                this.pagination.rowsNumber = data.total;
                this.pagination.page  = data.current_page;
            } finally {
                this.loading = false;
            }
        },
        onRequest(props) {
            this.pagination.page = props.pagination.page;
            this.loadCampaigns(props);
        },
        async submitApproval(id) {
            await axios.put(`/marketing/campaigns/${id}`, { status: 'pending_approval' });
            this.$q.notify({ type: 'info', message: 'Campaña enviada a aprobación.' });
            this.loadCampaigns();
        },
        async approveCampaign(id) {
            await axios.post(`/marketing/campaigns/${id}/approve`);
            this.$q.notify({ type: 'positive', message: 'Campaña aprobada.' });
            this.loadCampaigns();
            this.$emit('refresh-stats');
        },
        async activateCampaign(id) {
            const { data } = await axios.post(`/marketing/campaigns/${id}/activate`);
            this.$q.notify({ type: 'positive', message: data.message || 'Campaña activada.' });
            this.loadCampaigns();
            this.$emit('refresh-stats');
        },
        async pauseCampaign(id) {
            await axios.post(`/marketing/campaigns/${id}/pause`);
            this.$q.notify({ type: 'warning', message: 'Campaña pausada.' });
            this.loadCampaigns();
            this.$emit('refresh-stats');
        },
        async deleteCampaign(id) {
            this.$q.dialog({ title: 'Confirmar', message: '¿Eliminar esta campaña?', cancel: true })
                .onOk(async () => {
                    await axios.delete(`/marketing/campaigns/${id}`);
                    this.$q.notify({ type: 'positive', message: 'Campaña eliminada.' });
                    this.loadCampaigns();
                    this.$emit('refresh-stats');
                });
        },
        statusLabel(s) {
            return { draft: 'Borrador', pending_approval: 'Pend. aprobación', approved: 'Aprobada', active: 'Activa', paused: 'Pausada', finished: 'Terminada' }[s] || s;
        },
        statusColor(s) {
            return { draft: 'grey', pending_approval: 'orange', approved: 'info', active: 'positive', paused: 'warning', finished: 'dark' }[s] || 'grey';
        },
        channelColor(ch) {
            return { whatsapp: 'positive', facebook: 'primary', instagram: 'purple' }[ch] || 'grey';
        },
    },
};
</script>
