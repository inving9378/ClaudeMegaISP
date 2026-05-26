<template>
  <div class="marketing-lead-form">
    <div v-if="loading" class="text-center q-pa-xl"><q-spinner size="40px" color="primary" /></div>

    <div v-else-if="!formData" class="text-center q-pa-xl text-grey-6">
      Formulario no encontrado.
    </div>

    <div v-else-if="submitted" class="text-center q-pa-xl">
      <q-icon name="check_circle" color="positive" size="60px" class="q-mb-md" />
      <div class="text-h6 q-mb-sm">{{ formData.thank_you_message || '¡Gracias por tu información!' }}</div>
    </div>

    <q-form v-else @submit="submit" class="q-gutter-sm">
      <div v-if="formData.name" class="text-h6 q-mb-md">{{ formData.name }}</div>

      <template v-for="field in fields" :key="field.key">
        <q-input
          v-if="['text','email','tel','number','date'].includes(field.type)"
          v-model="values[field.key]"
          :label="field.label + (field.required ? ' *' : '')"
          :type="field.type"
          :rules="field.required ? [v => !!v || 'Campo requerido'] : []"
          outlined dense
        />
        <q-input
          v-else-if="field.type === 'textarea'"
          v-model="values[field.key]"
          :label="field.label + (field.required ? ' *' : '')"
          type="textarea" rows="3"
          :rules="field.required ? [v => !!v || 'Campo requerido'] : []"
          outlined dense
        />
        <q-select
          v-else-if="field.type === 'select'"
          v-model="values[field.key]"
          :label="field.label + (field.required ? ' *' : '')"
          :options="field.options || []"
          :rules="field.required ? [v => !!v || 'Campo requerido'] : []"
          outlined dense
        />
        <div v-else-if="field.type === 'radio'">
          <div class="text-caption text-grey-7 q-mb-xs">{{ field.label }}<span v-if="field.required"> *</span></div>
          <q-option-group v-model="values[field.key]" :options="(field.options||[]).map(o=>({label:o,value:o}))" type="radio" inline />
        </div>
        <q-checkbox v-else-if="field.type === 'checkbox'" v-model="values[field.key]" :label="field.label" />
      </template>

      <div class="q-mt-md">
        <q-btn type="submit" unelevated color="primary" label="Enviar" :loading="submitting" class="full-width" />
      </div>

      <div v-if="errorMsg" class="text-negative text-caption q-mt-sm">{{ errorMsg }}</div>
    </q-form>
  </div>
</template>

<script>
import axios from 'axios';
export default {
  name: 'MarketingLeadForm',
  props: {
    slug: { type: String, default: null },
    inline: { type: Boolean, default: false },
  },
  data() {
    return {
      loading: false, submitting: false, submitted: false,
      formData: null, values: {}, errorMsg: '',
    };
  },
  computed: {
    fields() { return this.formData?.fields_schema || []; }
  },
  mounted() {
    const s = this.slug || document.currentScript?.dataset?.form;
    if (s) this.loadForm(s);
  },
  methods: {
    async loadForm(slug) {
      this.loading = true;
      try {
        const { data } = await axios.get('/api/marketing/lead-forms?slug=' + slug);
        const list = data.data ?? data;
        this.formData = Array.isArray(list) ? list[0] : list;
        (this.formData?.fields_schema || []).forEach(f => { this.values[f.key] = ''; });
      } catch { this.formData = null; }
      finally { this.loading = false; }
    },
    async submit() {
      this.errorMsg = '';
      this.submitting = true;
      try {
        await axios.post('/public/marketing/lead-form/' + this.formData.slug + '/submit', this.values);
        this.submitted = true;
        if (this.formData.thank_you_redirect_url) {
          setTimeout(() => { window.location.href = this.formData.thank_you_redirect_url; }, 1500);
        }
      } catch (e) {
        this.errorMsg = e.response?.data?.message || 'Error al enviar. Intenta de nuevo.';
      } finally { this.submitting = false; }
    }
  }
}
</script>
