<template>
    <div class="q-pa-md" v-if="campaign">
        <!-- Header -->
        <div class="row items-center q-mb-md">
            <div class="col">
                <q-btn flat icon="arrow_back" label="Volver" @click="goBack" class="q-mr-sm" />
                <span class="text-h6">{{ campaign.title }}</span>
                <q-badge :color="statusColor(campaign.status)" :label="statusLabel(campaign.status)" class="q-ml-sm" />
            </div>
            <div class="col-auto q-gutter-sm">
                <q-btn v-if="campaign.status === 'approved'" color="positive" icon="play_arrow" label="Activar" :loading="acting" @click="activate" />
                <q-btn v-if="campaign.status === 'active'" color="warning" icon="pause" label="Pausar" :loading="acting" @click="pause" />
                <q-btn v-if="campaign.status === 'pending_approval'" color="positive" icon="check" label="Aprobar" :loading="acting" @click="approve" />
            </div>
        </div>

        <!-- Info card -->
        <q-card flat bordered class="q-mb-md">
            <q-card-section>
                <div class="row q-gutter-md">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="text-caption text-grey-6">Zona objetivo</div>
                        <div>{{ campaign.target_zone || '—' }}</div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="text-caption text-grey-6">Canales</div>
                        <div>
                            <q-badge v-for="ch in (campaign.channel || [])" :key="ch"
                                :color="channelColor(ch)" :label="ch" class="q-mr-xs" />
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="text-caption text-grey-6">Fechas</div>
                        <div>{{ campaign.start_date }} → {{ campaign.end_date }}</div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="text-caption text-grey-6">Límite diario</div>
                        <div>{{ campaign.daily_limit }} envíos/día</div>
                    </div>
                </div>
            </q-card-section>
        </q-card>

        <!-- Tabs -->
        <q-tabs v-model="tab" align="left" dense class="q-mb-md">
            <q-tab name="content" icon="edit_note" label="Contenido IA" />
            <q-tab name="schedules" icon="schedule" label="Programación" />
            <q-tab name="leads" icon="people" label="Leads" />
        </q-tabs>

        <q-tab-panels v-model="tab" animated>
            <!-- CONTENIDO IA -->
            <q-tab-panel name="content" class="q-pa-none">
                <marketing-content-generator :campaign-id="campaignId" @content-saved="loadContents" />
                <q-separator class="q-my-md" />
                <div class="text-subtitle2 q-mb-sm">Contenidos generados</div>
                <div class="row q-gutter-md">
                    <div class="col-12 col-md-6" v-for="c in contents" :key="c.id">
                        <q-card flat bordered>
                            <q-card-section>
                                <div class="row items-start no-wrap">
                                    <div class="col">
                                        <q-badge :color="contentTypeColor(c.content_type)" :label="c.content_type" class="q-mb-xs" />
                                        <q-badge :color="contentStatusColor(c.status)" :label="c.status" class="q-ml-xs q-mb-xs" />
                                        <div v-if="c.copy_text" class="text-body2 q-mt-xs">{{ c.copy_text }}</div>
                                        <q-img v-if="c.image_url" :src="c.image_url" style="max-height:200px" fit="contain" class="q-mt-xs" />
                                        <div v-if="c.image_prompt" class="text-caption text-grey-6 q-mt-xs">Prompt: {{ c.image_prompt }}</div>
                                    </div>
                                </div>
                            </q-card-section>
                            <q-card-actions v-if="c.status === 'pending'" align="right">
                                <q-btn flat dense color="positive" icon="check" label="Aprobar" @click="approveContent(c.id)" />
                                <q-btn flat dense color="negative" icon="close" label="Rechazar" @click="rejectContent(c.id)" />
                            </q-card-actions>
                        </q-card>
                    </div>
                    <div v-if="!contents.length" class="col-12 text-center text-grey-5 q-pa-xl">
                        No hay contenidos generados aún. Usa el generador de arriba.
                    </div>
                </div>
            </q-tab-panel>

            <!-- PROGRAMACIÓN -->
            <q-tab-panel name="schedules" class="q-pa-none">
                <q-table :rows="schedules" :columns="scheduleColumns" row-key="id" flat bordered :loading="loadingSchedules">
                    <template #body-cell-status="props">
                        <q-td :props="props">
                            <q-badge :color="scheduleStatusColor(props.value)" :label="props.value" />
                        </q-td>
                    </template>
                </q-table>
            </q-tab-panel>

            <!-- LEADS -->
            <q-tab-panel name="leads" class="q-pa-none">
                <marketing-leads :campaign-id="campaignId" />
            </q-tab-panel>
        </q-tab-panels>
    </div>
    <div v-else class="flex flex-center q-pa-xl">
        <q-spinner size="3rem" color="primary" />
    </div>
</template>

<script>
export default {
    name: 'MarketingCampaignShow',
    props: {
        campaignId: { type: [Number, String], required: true },
    },
    data() {
        return {
            campaign: null,
            contents: [],
            schedules: [],
            tab: 'content',
            acting: false,
            loadingSchedules: false,
            scheduleColumns: [
                { name: 'channel', label: 'Canal', field: 'channel', align: 'center' },
                { name: 'scheduled_at', label: 'Programado', field: 'scheduled_at', align: 'center', sortable: true },
                { name: 'published_at', label: 'Publicado', field: 'published_at', align: 'center' },
                { name: 'status', label: 'Estado', field: 'status', align: 'center' },
                { name: 'retry_count', label: 'Reintentos', field: 'retry_count', align: 'center' },
            ],
        };
    },
    mounted() {
        this.loadCampaign();
        this.loadContents();
        this.loadSchedules();
    },
    methods: {
        async loadCampaign() {
            const { data } = await axios.get(`/marketing/campaigns/${this.campaignId}`);
            this.campaign = data;
        },
        async loadContents() {
            const { data } = await axios.get(`/marketing/content/campaign/${this.campaignId}`);
            this.contents = data;
        },
        async loadSchedules() {
            this.loadingSchedules = true;
            try {
                const { data } = await axios.post('/marketing/campaigns/table', { campaign_id: this.campaignId });
                // schedules come from campaign detail; re-load campaign
                await this.loadCampaign();
            } finally {
                this.loadingSchedules = false;
            }
        },
        async approveContent(id) {
            await axios.post(`/marketing/content/approve/${id}`);
            this.$q.notify({ type: 'positive', message: 'Contenido aprobado.' });
            this.loadContents();
        },
        async rejectContent(id) {
            await axios.post(`/marketing/content/reject/${id}`);
            this.$q.notify({ type: 'warning', message: 'Contenido rechazado.' });
            this.loadContents();
        },
        async activate() {
            this.acting = true;
            try {
                const { data } = await axios.post(`/marketing/campaigns/${this.campaignId}/activate`);
                this.$q.notify({ type: 'positive', message: data.message });
                await this.loadCampaign();
            } finally { this.acting = false; }
        },
        async pause() {
            this.acting = true;
            try {
                await axios.post(`/marketing/campaigns/${this.campaignId}/pause`);
                this.$q.notify({ type: 'warning', message: 'Campaña pausada.' });
                await this.loadCampaign();
            } finally { this.acting = false; }
        },
        async approve() {
            this.acting = true;
            try {
                await axios.post(`/marketing/campaigns/${this.campaignId}/approve`);
                this.$q.notify({ type: 'positive', message: 'Campaña aprobada.' });
                await this.loadCampaign();
            } finally { this.acting = false; }
        },
        goBack() { window.history.back(); },
        statusLabel(s) {
            return { draft: 'Borrador', pending_approval: 'Pend. aprobación', approved: 'Aprobada', active: 'Activa', paused: 'Pausada', finished: 'Terminada' }[s] || s;
        },
        statusColor(s) {
            return { draft: 'grey', pending_approval: 'orange', approved: 'info', active: 'positive', paused: 'warning', finished: 'dark' }[s] || 'grey';
        },
        channelColor(ch) {
            return { whatsapp: 'positive', facebook: 'primary', instagram: 'purple' }[ch] || 'grey';
        },
        contentTypeColor(t) {
            return { text: 'primary', image: 'secondary', video: 'accent' }[t] || 'grey';
        },
        contentStatusColor(s) {
            return { pending: 'orange', approved: 'positive', rejected: 'negative' }[s] || 'grey';
        },
        scheduleStatusColor(s) {
            return { pending: 'orange', published: 'positive', failed: 'negative', skipped: 'grey' }[s] || 'grey';
        },
    },
};
</script>
