<template>
    <div class="q-pa-md">
        <!-- Header -->
        <div class="row items-center q-mb-md">
            <div class="col">
                <div class="text-h5 text-weight-bold">
                    <i class="fa fa-bullhorn q-mr-sm text-primary"></i> Marketing
                </div>
                <div class="text-caption text-grey-6">Gestión de campañas, contenido IA y leads</div>
            </div>
            <div class="col-auto">
                <q-btn color="primary" icon="add" label="Nueva campaña" @click="showCreateDialog = true" />
            </div>
        </div>

        <!-- Stats -->
        <div class="row q-gutter-md q-mb-lg">
            <div class="col-12 col-sm-6 col-md-3" v-for="stat in statCards" :key="stat.label">
                <q-card flat bordered>
                    <q-card-section class="q-pa-md">
                        <div class="row items-center no-wrap">
                            <div class="col">
                                <div class="text-caption text-grey-6">{{ stat.label }}</div>
                                <div class="text-h5 text-weight-bold">{{ stat.value }}</div>
                            </div>
                            <div class="col-auto">
                                <q-icon :name="stat.icon" :color="stat.color" size="2rem" />
                            </div>
                        </div>
                    </q-card-section>
                </q-card>
            </div>
        </div>

        <!-- Tabs -->
        <q-tabs v-model="activeTab" align="left" class="q-mb-md" dense>
            <q-tab name="campaigns" icon="campaign" label="Campañas" />
            <q-tab name="leads" icon="people" label="Leads" />
            <q-tab name="templates" icon="description" label="Plantillas" />
            <q-tab name="conversations" icon="chat" label="Conversaciones">
                <q-badge v-if="totalUnread > 0" color="negative" :label="totalUnread" floating rounded />
            </q-tab>
        </q-tabs>

        <q-tab-panels v-model="activeTab" animated>
            <q-tab-panel name="campaigns" class="q-pa-none">
                <marketing-campaigns @campaign-selected="openCampaign" @refresh-stats="loadStats" />
            </q-tab-panel>
            <q-tab-panel name="leads" class="q-pa-none">
                <marketing-leads />
            </q-tab-panel>
            <q-tab-panel name="conversations" class="q-pa-none" style="height:calc(100vh - 200px)">
                <marketing-conversations-view :auth-user-id="authUserId" />
            </q-tab-panel>
            <q-tab-panel name="templates" class="q-pa-none">
                <!-- Tabla simple de plantillas -->
                <div class="row justify-end q-mb-md">
                    <q-btn color="secondary" icon="add" label="Nueva plantilla" @click="showTemplateDialog = true" />
                </div>
                <q-table
                    :rows="templates"
                    :columns="templateColumns"
                    row-key="id"
                    flat
                    bordered
                    :loading="loadingTemplates"
                >
                    <template #body-cell-channel="props">
                        <q-td :props="props">
                            <q-badge :color="channelColor(props.value)" :label="props.value" />
                        </q-td>
                    </template>
                    <template #body-cell-is_active="props">
                        <q-td :props="props">
                            <q-badge :color="props.value ? 'positive' : 'grey'" :label="props.value ? 'Activa' : 'Inactiva'" />
                        </q-td>
                    </template>
                    <template #body-cell-actions="props">
                        <q-td :props="props">
                            <q-btn flat dense icon="edit" color="primary" @click="editTemplate(props.row)" />
                            <q-btn flat dense icon="delete" color="negative" @click="deleteTemplate(props.row.id)" />
                        </q-td>
                    </template>
                </q-table>
            </q-tab-panel>
        </q-tab-panels>

        <!-- Dialog: Crear campaña -->
        <q-dialog v-model="showCreateDialog" persistent>
            <q-card style="min-width:500px">
                <q-card-section class="bg-primary text-white">
                    <div class="text-h6">Nueva campaña</div>
                </q-card-section>
                <q-card-section class="q-gutter-sm">
                    <q-input v-model="form.title" label="Título *" outlined dense />
                    <q-input v-model="form.description" label="Descripción" outlined dense type="textarea" rows="2" />
                    <q-input v-model="form.target_zone" label="Zona objetivo" outlined dense />
                    <q-option-group
                        v-model="form.channel"
                        :options="channelOptions"
                        type="checkbox"
                        inline
                        dense
                    />
                    <div class="row q-gutter-sm">
                        <q-input v-model="form.start_date" label="Inicio *" outlined dense type="date" class="col" />
                        <q-input v-model="form.end_date" label="Fin *" outlined dense type="date" class="col" />
                    </div>
                    <q-input v-model.number="form.daily_limit" label="Límite diario" outlined dense type="number" />
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" @click="showCreateDialog = false" />
                    <q-btn color="primary" label="Crear" :loading="saving" @click="createCampaign" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Dialog: Plantilla -->
        <q-dialog v-model="showTemplateDialog" persistent>
            <q-card style="min-width:520px">
                <q-card-section class="bg-secondary text-white">
                    <div class="text-h6">{{ editingTemplate ? 'Editar' : 'Nueva' }} plantilla</div>
                </q-card-section>
                <q-card-section class="q-gutter-sm">
                    <q-input v-model="templateForm.name" label="Nombre *" outlined dense />
                    <q-select v-model="templateForm.channel" :options="['whatsapp','facebook','instagram']" label="Canal *" outlined dense />
                    <q-select v-model="templateForm.template_type" :options="templateTypeOptions" label="Tipo *" outlined dense emit-value map-options />
                    <q-input v-model="templateForm.system_prompt" label="Instrucciones para IA" outlined dense type="textarea" rows="3" />
                    <q-input v-model="templateForm.base_copy" label="Copy base *" outlined dense type="textarea" rows="3" />
                    <q-toggle v-model="templateForm.is_active" label="Activa" />
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" @click="closeTemplateDialog" />
                    <q-btn color="secondary" label="Guardar" :loading="savingTemplate" @click="saveTemplate" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
export default {
    name: 'MarketingDashboard',
    props: {
        authUserId: { type: Number, default: null },
    },
    data() {
        return {
            activeTab: 'campaigns',
            stats: {},
            templates: [],
            totalUnread: 0,
            loadingTemplates: false,
            showCreateDialog: false,
            showTemplateDialog: false,
            editingTemplate: null,
            saving: false,
            savingTemplate: false,
            form: {
                title: '',
                description: '',
                target_zone: '',
                channel: ['whatsapp'],
                daily_limit: 100,
                start_date: '',
                end_date: '',
            },
            templateForm: {
                name: '',
                channel: 'whatsapp',
                template_type: 'campaign',
                system_prompt: '',
                base_copy: '',
                is_active: true,
            },
            channelOptions: [
                { label: 'WhatsApp', value: 'whatsapp' },
                { label: 'Facebook', value: 'facebook' },
                { label: 'Instagram', value: 'instagram' },
            ],
            templateTypeOptions: [
                { label: 'Campaña', value: 'campaign' },
                { label: 'Bienvenida', value: 'welcome' },
                { label: 'Seguimiento', value: 'followup' },
                { label: 'Cierre', value: 'closing' },
            ],
            templateColumns: [
                { name: 'name', label: 'Nombre', field: 'name', align: 'left', sortable: true },
                { name: 'channel', label: 'Canal', field: 'channel', align: 'center' },
                { name: 'template_type', label: 'Tipo', field: 'template_type', align: 'center' },
                { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' },
                { name: 'actions', label: 'Acciones', field: 'actions', align: 'center' },
            ],
        };
    },
    computed: {
        statCards() {
            const c = this.stats.campaigns || {};
            const l = this.stats.leads || {};
            return [
                { label: 'Campañas activas', value: c.active || 0, icon: 'campaign', color: 'positive' },
                { label: 'Campañas totales', value: c.total || 0, icon: 'bar_chart', color: 'primary' },
                { label: 'Leads nuevos', value: l.new || 0, icon: 'person_add', color: 'warning' },
                { label: 'Convertidos', value: l.converted || 0, icon: 'check_circle', color: 'positive' },
            ];
        },
    },
    mounted() {
        this.loadStats();
        this.loadTemplates();
        this.loadUnreadCount();
        this.unreadInterval = setInterval(this.loadUnreadCount, 10000);
        const params = new URLSearchParams(window.location.search);
        if (params.get('tab')) this.activeTab = params.get('tab');
    },
    beforeUnmount() {
        clearInterval(this.unreadInterval);
    },
    methods: {
        async loadStats() {
            try {
                const { data } = await axios.get('/marketing/stats');
                this.stats = data;
            } catch {}
        },
        async loadTemplates() {
            this.loadingTemplates = true;
            try {
                const { data } = await axios.get('/marketing/templates');
                this.templates = data;
            } finally {
                this.loadingTemplates = false;
            }
        },
        async createCampaign() {
            if (!this.form.title || !this.form.start_date || !this.form.end_date) {
                this.$q.notify({ type: 'warning', message: 'Completa los campos obligatorios.' });
                return;
            }
            this.saving = true;
            try {
                await axios.post('/marketing/campaigns/store', this.form);
                this.$q.notify({ type: 'positive', message: 'Campaña creada correctamente.' });
                this.showCreateDialog = false;
                this.resetForm();
                this.loadStats();
                this.activeTab = 'campaigns';
            } catch (e) {
                this.$q.notify({ type: 'negative', message: e.response?.data?.message || 'Error al crear campaña.' });
            } finally {
                this.saving = false;
            }
        },
        async saveTemplate() {
            this.savingTemplate = true;
            try {
                if (this.editingTemplate) {
                    await axios.put(`/marketing/templates/${this.editingTemplate.id}`, this.templateForm);
                } else {
                    await axios.post('/marketing/templates/store', this.templateForm);
                }
                this.$q.notify({ type: 'positive', message: 'Plantilla guardada.' });
                this.closeTemplateDialog();
                this.loadTemplates();
            } catch (e) {
                this.$q.notify({ type: 'negative', message: e.response?.data?.message || 'Error al guardar.' });
            } finally {
                this.savingTemplate = false;
            }
        },
        async deleteTemplate(id) {
            this.$q.dialog({
                title: 'Confirmar',
                message: '¿Eliminar esta plantilla?',
                cancel: true,
            }).onOk(async () => {
                await axios.delete(`/marketing/templates/${id}`);
                this.$q.notify({ type: 'positive', message: 'Plantilla eliminada.' });
                this.loadTemplates();
            });
        },
        editTemplate(template) {
            this.editingTemplate = template;
            this.templateForm = { ...template };
            this.showTemplateDialog = true;
        },
        closeTemplateDialog() {
            this.showTemplateDialog = false;
            this.editingTemplate = null;
            this.templateForm = { name: '', channel: 'whatsapp', template_type: 'campaign', system_prompt: '', base_copy: '', is_active: true };
        },
        openCampaign(id) {
            window.location.href = `/marketing/campaigns/${id}`;
        },
        resetForm() {
            this.form = { title: '', description: '', target_zone: '', channel: ['whatsapp'], daily_limit: 100, start_date: '', end_date: '' };
        },
        channelColor(channel) {
            return { whatsapp: 'positive', facebook: 'primary', instagram: 'purple' }[channel] || 'grey';
        },
        async loadUnreadCount() {
            try {
                const { data } = await axios.get('/api/marketing/conversations', { params: { per_page: 1 } });
                // Sum unread from meta or load separately
                const { data: all } = await axios.get('/api/marketing/conversations', { params: { per_page: 100 } });
                this.totalUnread = (all.data || []).reduce((sum, c) => sum + (c.unread_count || 0), 0);
            } catch {}
        },
    },
};
</script>
