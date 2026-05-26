<template>
    <div>
        <!-- Filtros -->
        <div class="row q-gutter-sm q-mb-md">
            <q-input v-model="filters.search" label="Buscar" outlined dense clearable class="col-12 col-sm-4"
                @update:model-value="loadLeads">
                <template #append><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="filters.status" :options="statusOptions" label="Estado" outlined dense clearable
                class="col-12 col-sm-3" emit-value map-options @update:model-value="loadLeads" />
            <q-select v-model="filters.channel" :options="['whatsapp','facebook','instagram']" label="Canal"
                outlined dense clearable class="col-12 col-sm-2" @update:model-value="loadLeads" />
        </div>

        <!-- Tabla -->
        <q-table
            :rows="leads"
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
            <template #body-cell-qualification_score="props">
                <q-td :props="props">
                    <span v-if="props.value !== null">
                        <q-badge :color="scoreColor(props.value)" :label="props.value + ' – ' + scoreLabel(props.value)" />
                    </span>
                    <span v-else class="text-grey-5">Sin calificar</span>
                </q-td>
            </template>
            <template #body-cell-actions="props">
                <q-td :props="props">
                    <q-btn flat dense icon="psychology" color="primary" :loading="qualifying === props.row.id"
                        @click="qualify(props.row)">
                        <q-tooltip>Calificar con IA</q-tooltip>
                    </q-btn>
                    <q-btn flat dense icon="edit" color="secondary" @click="openStatusDialog(props.row)">
                        <q-tooltip>Cambiar estado</q-tooltip>
                    </q-btn>
                    <q-btn flat dense icon="person" color="grey" @click="openAssignDialog(props.row)">
                        <q-tooltip>Asignar agente</q-tooltip>
                    </q-btn>
                </q-td>
            </template>
        </q-table>

        <!-- Dialog cambiar estado -->
        <q-dialog v-model="showStatusDialog">
            <q-card style="min-width:320px">
                <q-card-section class="bg-primary text-white">
                    <div class="text-h6">Cambiar estado</div>
                </q-card-section>
                <q-card-section>
                    <q-select v-model="newStatus" :options="statusOptions" label="Nuevo estado"
                        outlined dense emit-value map-options />
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" @click="showStatusDialog = false" />
                    <q-btn color="primary" label="Guardar" @click="updateStatus" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Dialog calificación -->
        <q-dialog v-model="showQualification">
            <q-card style="min-width:400px">
                <q-card-section class="text-h6">Resultado de calificación</q-card-section>
                <q-card-section v-if="lastQualification">
                    <div class="row q-gutter-sm q-mb-sm">
                        <q-badge :color="scoreColor(lastQualification.score)"
                            :label="lastQualification.score + ' – ' + scoreLabel(lastQualification.score)"
                            class="text-subtitle2" />
                    </div>
                    <div class="q-mb-sm"><strong>Notas:</strong> {{ lastQualification.notes }}</div>
                    <div class="q-mb-sm"><strong>Próxima acción:</strong> {{ lastQualification.next_action }}</div>
                    <div v-if="lastQualification.signals">
                        <div><strong>Interés:</strong> {{ lastQualification.signals.interest_level }}</div>
                        <div><strong>Timeline:</strong> {{ lastQualification.signals.timeline }}</div>
                        <div v-if="lastQualification.signals.objections?.length">
                            <strong>Objeciones:</strong> {{ lastQualification.signals.objections.join(', ') }}
                        </div>
                    </div>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cerrar" @click="showQualification = false" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
export default {
    name: 'MarketingLeads',
    props: {
        campaignId: { type: [Number, String], default: null },
    },
    data() {
        return {
            leads: [],
            loading: false,
            qualifying: null,
            filters: { search: '', status: null, channel: null },
            pagination: { page: 1, rowsPerPage: 20, rowsNumber: 0 },
            showStatusDialog: false,
            showQualification: false,
            selectedLead: null,
            newStatus: null,
            lastQualification: null,
            statusOptions: [
                { label: 'Nuevo', value: 'new' },
                { label: 'Contactado', value: 'contacted' },
                { label: 'Calificado', value: 'qualified' },
                { label: 'No calificado', value: 'unqualified' },
                { label: 'Agendado', value: 'scheduled' },
                { label: 'Convertido', value: 'converted' },
                { label: 'Perdido', value: 'lost' },
            ],
            columns: [
                { name: 'contact_name', label: 'Nombre', field: 'contact_name', align: 'left', sortable: true },
                { name: 'contact_identifier', label: 'Contacto', field: 'contact_identifier', align: 'left' },
                { name: 'channel', label: 'Canal', field: 'channel', align: 'center' },
                { name: 'status', label: 'Estado', field: 'status', align: 'center' },
                { name: 'qualification_score', label: 'Calificación', field: 'qualification_score', align: 'center', sortable: true },
                { name: 'created_at', label: 'Fecha', field: 'created_at', align: 'center', sortable: true },
                { name: 'actions', label: 'Acciones', field: 'actions', align: 'center' },
            ],
        };
    },
    mounted() {
        this.loadLeads();
    },
    methods: {
        async loadLeads(props) {
            this.loading = true;
            const page = props?.pagination?.page || this.pagination.page;
            try {
                const { data } = await axios.post('/marketing/leads/table', {
                    ...this.filters,
                    campaign_id: this.campaignId || undefined,
                    page,
                });
                this.leads = data.data;
                this.pagination.rowsNumber = data.total;
                this.pagination.page       = data.current_page;
            } finally {
                this.loading = false;
            }
        },
        onRequest(props) {
            this.pagination.page = props.pagination.page;
            this.loadLeads(props);
        },
        async qualify(lead) {
            this.qualifying = lead.id;
            try {
                const { data } = await axios.post(`/marketing/leads/${lead.id}/qualify`);
                this.lastQualification = data.qualification;
                this.showQualification = true;
                this.loadLeads();
            } catch (e) {
                this.$q.notify({ type: 'negative', message: 'Error al calificar lead.' });
            } finally {
                this.qualifying = null;
            }
        },
        openStatusDialog(lead) {
            this.selectedLead = lead;
            this.newStatus    = lead.status;
            this.showStatusDialog = true;
        },
        async updateStatus() {
            await axios.put(`/marketing/leads/${this.selectedLead.id}/status`, { status: this.newStatus });
            this.$q.notify({ type: 'positive', message: 'Estado actualizado.' });
            this.showStatusDialog = false;
            this.loadLeads();
        },
        openAssignDialog(lead) {
            this.$q.dialog({
                title: 'Asignar agente',
                prompt: { model: '', type: 'number', label: 'ID del usuario' },
                cancel: true,
            }).onOk(async (userId) => {
                if (!userId) return;
                await axios.post(`/marketing/leads/${lead.id}/assign`, { user_id: userId });
                this.$q.notify({ type: 'positive', message: 'Lead asignado.' });
                this.loadLeads();
            });
        },
        statusLabel(s) {
            return { new: 'Nuevo', contacted: 'Contactado', qualified: 'Calificado', unqualified: 'No calificado', scheduled: 'Agendado', converted: 'Convertido', lost: 'Perdido' }[s] || s;
        },
        statusColor(s) {
            return { new: 'blue', contacted: 'orange', qualified: 'positive', unqualified: 'grey', scheduled: 'cyan', converted: 'green-8', lost: 'negative' }[s] || 'grey';
        },
        scoreLabel(score) {
            if (score >= 80) return 'Hot';
            if (score >= 50) return 'Warm';
            if (score >= 20) return 'Cold';
            return 'Frío';
        },
        scoreColor(score) {
            if (score >= 80) return 'negative';
            if (score >= 50) return 'warning';
            if (score >= 20) return 'info';
            return 'grey';
        },
    },
};
</script>
