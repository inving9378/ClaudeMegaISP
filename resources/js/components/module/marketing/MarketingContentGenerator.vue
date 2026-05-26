<template>
    <q-card flat bordered class="q-mb-md">
        <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-md">
                <q-icon name="smart_toy" color="primary" class="q-mr-xs" /> Generador de contenido IA
            </div>

            <q-tabs v-model="genTab" dense align="left" class="q-mb-md">
                <q-tab name="copy" icon="text_fields" label="Copy de texto" />
                <q-tab name="image" icon="image" label="Imagen SDXL" />
            </q-tabs>

            <q-tab-panels v-model="genTab" animated>
                <!-- COPY -->
                <q-tab-panel name="copy" class="q-pa-none">
                    <div class="row q-gutter-sm items-end">
                        <q-select
                            v-model="selectedTemplate"
                            :options="templates"
                            option-label="name"
                            option-value="id"
                            label="Plantilla de IA *"
                            outlined
                            dense
                            class="col-12 col-sm-6"
                            emit-value
                            map-options
                            :loading="loadingTemplates"
                        />
                        <q-btn
                            color="primary"
                            icon="auto_awesome"
                            label="Generar variaciones"
                            :loading="generatingCopy"
                            :disable="!selectedTemplate"
                            @click="generateCopy"
                        />
                    </div>
                    <div v-if="generatedCopy.length" class="q-mt-md">
                        <div class="text-caption text-grey-6 q-mb-xs">
                            {{ generatedCopy.length }} variaciones generadas y guardadas como pendientes de aprobación.
                        </div>
                        <q-card v-for="v in generatedCopy" :key="v.id" flat bordered class="q-mb-sm">
                            <q-card-section class="q-pa-sm">
                                <div class="text-caption text-grey-5">Variación {{ v.variation_index + 1 }}</div>
                                <div>{{ v.copy_text }}</div>
                            </q-card-section>
                        </q-card>
                    </div>
                </q-tab-panel>

                <!-- IMAGEN -->
                <q-tab-panel name="image" class="q-pa-none">
                    <div class="row q-gutter-sm items-start">
                        <q-input
                            v-model="customPrompt"
                            label="Prompt personalizado (opcional — si vacío, lo genera la IA)"
                            outlined
                            dense
                            type="textarea"
                            rows="2"
                            class="col-12 col-sm-8"
                        />
                        <q-btn
                            color="secondary"
                            icon="image"
                            label="Generar imagen"
                            :loading="generatingImage"
                            @click="generateImage"
                            class="col-auto"
                        />
                    </div>
                    <div v-if="generatedImage" class="q-mt-md">
                        <div class="text-caption text-grey-6 q-mb-xs">Imagen generada con SDXL vía Replicate</div>
                        <q-img :src="generatedImage.image_url" style="max-height:300px;max-width:300px" fit="contain" />
                        <div class="text-caption text-grey-5 q-mt-xs">Prompt: {{ generatedImage.image_prompt }}</div>
                    </div>
                    <q-banner v-if="imageError" class="bg-negative text-white q-mt-sm" rounded>
                        {{ imageError }}
                    </q-banner>
                </q-tab-panel>
            </q-tab-panels>
        </q-card-section>
    </q-card>
</template>

<script>
export default {
    name: 'MarketingContentGenerator',
    props: {
        campaignId: { type: [Number, String], required: true },
    },
    emits: ['content-saved'],
    data() {
        return {
            genTab: 'copy',
            templates: [],
            selectedTemplate: null,
            loadingTemplates: false,
            generatingCopy: false,
            generatingImage: false,
            generatedCopy: [],
            generatedImage: null,
            customPrompt: '',
            imageError: '',
        };
    },
    mounted() {
        this.loadTemplates();
    },
    methods: {
        async loadTemplates() {
            this.loadingTemplates = true;
            try {
                const { data } = await axios.get('/marketing/templates');
                this.templates = data;
            } finally {
                this.loadingTemplates = false;
            }
        },
        async generateCopy() {
            this.generatingCopy = true;
            this.generatedCopy = [];
            try {
                const { data } = await axios.post('/marketing/content/generate-copy', {
                    campaign_id: this.campaignId,
                    template_id: this.selectedTemplate,
                });
                this.generatedCopy = data.contents || [];
                this.$q.notify({ type: 'positive', message: data.message });
                this.$emit('content-saved');
            } catch (e) {
                this.$q.notify({ type: 'negative', message: e.response?.data?.error || 'Error al generar copy.' });
            } finally {
                this.generatingCopy = false;
            }
        },
        async generateImage() {
            this.generatingImage = true;
            this.generatedImage  = null;
            this.imageError      = '';
            try {
                const { data } = await axios.post('/marketing/content/generate-image', {
                    campaign_id:   this.campaignId,
                    custom_prompt: this.customPrompt || undefined,
                });
                this.generatedImage = data.content;
                this.$q.notify({ type: 'positive', message: data.message });
                this.$emit('content-saved');
            } catch (e) {
                this.imageError = e.response?.data?.error || 'Error al generar imagen. Verifica REPLICATEAPITOKEN.';
            } finally {
                this.generatingImage = false;
            }
        },
    },
};
</script>
