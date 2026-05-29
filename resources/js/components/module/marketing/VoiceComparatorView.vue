<template>
  <div class="q-pa-md">
    <div class="text-h5 q-mb-md">
      <i class="fa fa-microphone q-mr-sm text-primary"></i>
      Comparador de Voces TTS
    </div>

    <!-- Texto de prueba -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-subtitle1 q-mb-sm">1. Texto de prueba</div>
        <q-input
          v-model="testText"
          type="textarea"
          outlined
          autogrow
          hint="Este texto se sintetizará con cada combinación de voz + modelo"
        />
      </q-card-section>
    </q-card>

    <!-- Selección voces y modelos -->
    <div class="row q-gutter-md q-mb-md">
      <q-card flat bordered class="col">
        <q-card-section>
          <div class="text-subtitle1 q-mb-sm">2. Voces</div>
          <q-checkbox
            v-for="v in voices"
            :key="v.id"
            v-model="selectedVoices"
            :val="v.id"
            :label="`${v.id} — ${v.description} (${v.gender})`"
            class="q-mr-md"
          />
        </q-card-section>
      </q-card>

      <q-card flat bordered class="col-auto">
        <q-card-section>
          <div class="text-subtitle1 q-mb-sm">3. Modelos</div>
          <q-checkbox
            v-for="m in models"
            :key="m.id"
            v-model="selectedModels"
            :val="m.id"
            :label="`${m.name} ($${m.cost_per_1m_chars}/1M chars)`"
            class="q-mr-md"
          />
        </q-card-section>
      </q-card>
    </div>

    <!-- Botón generar -->
    <div class="q-mb-md row items-center q-gutter-sm">
      <q-btn
        color="primary"
        icon="fa fa-play"
        label="Generar samples"
        :loading="generating"
        :disable="!selectedVoices.length || !selectedModels.length || !testText"
        @click="generateSamples"
      />
      <div class="text-caption text-grey-7">
        {{ selectedVoices.length * selectedModels.length }} combinaciones
        · Costo estimado: ${{ estimatedCost.toFixed(4) }} USD
        (≈ ${{ (estimatedCost * 20).toFixed(2) }} MXN)
      </div>
    </div>

    <!-- Error -->
    <q-banner v-if="errorMsg" class="q-mb-md bg-negative text-white" rounded>
      <template #avatar><q-icon name="error" /></template>
      {{ errorMsg }}
    </q-banner>

    <!-- Resultado de generación -->
    <q-card v-if="samples.length" flat bordered class="q-mb-md">
      <q-card-section>
        <div class="row items-center justify-between q-mb-sm">
          <div class="text-subtitle1">4. Samples generados</div>
          <div class="text-caption text-grey-7">
            Total: ${{ lastTotalCost.toFixed(4) }} USD
          </div>
        </div>

        <div
          v-for="sample in samples"
          :key="sample.label"
          class="q-pa-sm q-mb-sm rounded-borders"
          style="border: 1px solid #e0e0e0;"
        >
          <div class="row items-center q-gutter-sm">
            <div class="col-auto" style="min-width: 180px;">
              <div class="text-weight-medium">{{ sample.voice }}</div>
              <div class="text-caption text-grey-7">
                {{ sample.model }}
                <span v-if="sample.duration_sec"> · {{ sample.duration_sec.toFixed(1) }}s</span>
                <span> · ${{ (sample.cost_usd || 0).toFixed(4) }}</span>
              </div>
            </div>

            <div class="col">
              <audio :src="sample.url" controls preload="none" style="width: 100%; height: 36px;" />
            </div>

            <div class="col-auto" style="min-width: 200px;">
              <q-select
                v-model="sample.assignTo"
                :options="nicheOptions"
                label="Asignar a nicho"
                dense
                outlined
                emit-value
                map-options
                clearable
                @update:model-value="(v) => v && assignToNiche(sample, v)"
              />
            </div>
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Tabla voces por nicho -->
    <q-card flat bordered>
      <q-card-section>
        <div class="text-subtitle1 q-mb-sm">5. Voces asignadas por nicho</div>

        <q-table
          :rows="niches"
          :columns="nicheColumns"
          row-key="id"
          flat
          dense
          hide-pagination
          :rows-per-page-options="[0]"
        >
          <template #body-cell-preferred_voice_id="props">
            <q-td :props="props">
              <q-badge
                :color="props.value ? 'primary' : 'grey'"
                :label="props.value || 'sin asignar'"
              />
            </q-td>
          </template>

          <template #body-cell-preferred_voice_model="props">
            <q-td :props="props">
              <span class="text-caption">{{ props.value || 'tts-1' }}</span>
            </q-td>
          </template>

          <template #body-cell-actions="props">
            <q-td :props="props">
              <q-btn
                flat
                dense
                size="sm"
                icon="fa fa-edit"
                color="primary"
                @click="openEditModal(props.row)"
              />
            </q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>

    <!-- Modal editar voz por nicho -->
    <q-dialog v-model="editModal">
      <q-card style="min-width: 380px;">
        <q-card-section>
          <div class="text-h6">Editar voz: {{ editingNiche?.name }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-select
            v-model="editForm.voice_id"
            :options="voiceOptions"
            label="Voz"
            outlined
            emit-value
            map-options
            class="q-mb-sm"
          />
          <q-select
            v-model="editForm.voice_model"
            :options="modelOptions"
            label="Modelo"
            outlined
            emit-value
            map-options
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn
            color="primary"
            label="Guardar"
            :loading="savingEdit"
            @click="saveEdit"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const testText = ref(
  'Hola, soy Meganet. Llámanos al cinco, cinco, seis, ocho, uno, siete, cinco, seis, cuatro, tres para contratar tu plan de internet.'
);

const voices         = ref([]);
const models         = ref([]);
const niches         = ref([]);
const samples        = ref([]);
const generating     = ref(false);
const errorMsg       = ref('');
const lastTotalCost  = ref(0);
const selectedVoices = ref(['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer']);
const selectedModels = ref(['tts-1', 'tts-1-hd']);

const editModal     = ref(false);
const editingNiche  = ref(null);
const savingEdit    = ref(false);
const editForm      = ref({ voice_id: '', voice_model: 'tts-1' });

const estimatedCost = computed(() => {
  const chars = testText.value.length;
  let cost = 0;
  selectedModels.value.forEach(m => {
    const pricePerChar = m === 'tts-1-hd' ? 30 / 1_000_000 : 15 / 1_000_000;
    cost += chars * pricePerChar * selectedVoices.value.length;
  });
  return cost;
});

const nicheOptions = computed(() =>
  niches.value.map(n => ({ label: n.name, value: n.id }))
);

const voiceOptions = computed(() =>
  voices.value.map(v => ({ label: `${v.id} — ${v.description}`, value: v.id }))
);

const modelOptions = computed(() =>
  models.value.map(m => ({ label: m.name, value: m.id }))
);

const nicheColumns = [
  { name: 'name',                  label: 'Nicho',   field: 'name',                  align: 'left' },
  { name: 'preferred_voice_id',    label: 'Voz',     field: 'preferred_voice_id',    align: 'left' },
  { name: 'preferred_voice_model', label: 'Modelo',  field: 'preferred_voice_model', align: 'left' },
  { name: 'actions',               label: '',        field: 'id',                    align: 'right' },
];

async function loadInitial() {
  try {
    const r = await axios.get('/api/marketing/voice-comparator/voices');
    voices.value  = r.data.voices;
    models.value  = r.data.models;
    niches.value  = r.data.niches;
  } catch (e) {
    errorMsg.value = 'Error cargando datos: ' + (e.response?.data?.message || e.message);
  }
}

async function generateSamples() {
  generating.value = true;
  errorMsg.value   = '';
  samples.value    = [];

  try {
    const r = await axios.post('/api/marketing/voice-comparator/generate-samples', {
      text:   testText.value,
      voices: selectedVoices.value,
      models: selectedModels.value,
    });
    samples.value   = r.data.samples.map(s => ({ ...s, assignTo: null }));
    lastTotalCost.value = r.data.total_cost_usd;
  } catch (e) {
    errorMsg.value = 'Error generando samples: ' + (e.response?.data?.message || e.message);
  } finally {
    generating.value = false;
  }
}

async function assignToNiche(sample, nicheId) {
  try {
    await axios.post('/api/marketing/voice-comparator/assign-niche', {
      niche_id:    nicheId,
      voice_id:    sample.voice,
      voice_model: sample.model,
    });
    await loadInitial();
    sample.assignTo = null;
  } catch (e) {
    errorMsg.value = 'Error asignando voz: ' + (e.response?.data?.message || e.message);
  }
}

function openEditModal(niche) {
  editingNiche.value = niche;
  editForm.value = {
    voice_id:    niche.preferred_voice_id    || 'nova',
    voice_model: niche.preferred_voice_model || 'tts-1',
  };
  editModal.value = true;
}

async function saveEdit() {
  savingEdit.value = true;
  try {
    await axios.post('/api/marketing/voice-comparator/assign-niche', {
      niche_id:    editingNiche.value.id,
      voice_id:    editForm.value.voice_id,
      voice_model: editForm.value.voice_model,
    });
    editModal.value = false;
    await loadInitial();
  } catch (e) {
    errorMsg.value = 'Error guardando: ' + (e.response?.data?.message || e.message);
  } finally {
    savingEdit.value = false;
  }
}

onMounted(loadInitial);
</script>
