<template>
    <section>
        <h6 class="flt-section-title">Galería</h6>
        <div class="row g-3">
            <div class="col-6 col-md-3" v-for="slot in photoSlots" :key="slot.key">
                <div class="flt-photo-slot">
                    <div class="flt-photo-slot-head">{{ slot.label }}</div>
                    <div v-if="byType[slot.key]" class="flt-photo-thumb" @click="zoom = byType[slot.key]">
                        <img :src="photoUrl(byType[slot.key])" @error="onImgError" />
                        <button class="flt-photo-del" @click.stop="remove(byType[slot.key])"><i class="bi bi-trash"></i></button>
                    </div>
                    <div v-else class="flt-photo-empty" @click="pick(slot.key)">
                        <i class="bi bi-camera"></i><span>Subir</span>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="flt-section-title mt-4">Fotos adicionales</h6>
        <div class="row g-3">
            <div class="col-6 col-md-3" v-for="p in extras" :key="p.id">
                <div class="flt-photo-thumb" @click="zoom = p">
                    <img :src="photoUrl(p)" @error="onImgError" />
                    <button class="flt-photo-del" @click.stop="remove(p)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="flt-photo-empty" style="height:100%;min-height:120px" @click="pick('general')">
                    <i class="bi bi-plus-lg"></i><span>Agregar</span>
                </div>
            </div>
        </div>

        <input ref="fileInput" type="file" accept="image/*" class="d-none" @change="onPick" />

        <!-- Zoom modal -->
        <div v-if="zoom" class="flt-zoom" @click="zoom = null">
            <img :src="photoUrl(zoom)" @error="onImgError" />
        </div>
    </section>
</template>

<script>
import { ref, computed } from 'vue';
import axios from 'axios';

export default {
    name: 'FleetTabFotos',
    props: {
        vehicle:   { type: Object, required: true },
        vehicleId: { type: [String, Number], required: true },
        baseUrl:   { type: String, default: '/flotas' },
    },
    emits: ['reload', 'toast'],
    setup(props, { emit }) {
        const photoSlots = [
            { key: 'front', label: 'Frente' }, { key: 'side', label: 'Lateral' },
            { key: 'rear', label: 'Trasera' }, { key: 'dashboard', label: 'Tablero' },
        ];

        const byType = computed(() => {
            const map = {};
            (props.vehicle?.photos ?? []).forEach((p) => { if (p.photo_type !== 'general' && !map[p.photo_type]) map[p.photo_type] = p; });
            return map;
        });
        const extras = computed(() => (props.vehicle?.photos ?? []).filter((p) => p.photo_type === 'general'));
        const photoUrl = (p) => (p?.file_path ? `/storage/${p.file_path}` : '');
        const onImgError = (e) => { e.target.style.display = 'none'; e.target.parentElement.classList.add('flt-photo-broken'); };

        const zoom = ref(null);
        const fileInput = ref(null);
        const pendingType = ref('general');
        function pick(type) { pendingType.value = type; fileInput.value.click(); }

        async function onPick(e) {
            const file = e.target.files[0]; e.target.value = '';
            if (!file) return;
            try {
                const fd = new FormData();
                fd.append('photo', file); fd.append('vehicle_id', props.vehicleId); fd.append('photo_type', pendingType.value);
                await axios.post(`${props.baseUrl}/api/fotos`, fd);
                emit('reload'); emit('toast', { message: 'Foto subida.', type: 'success' });
            } catch (e) { emit('toast', { message: 'No se pudo subir la foto.', type: 'error' }); }
        }

        async function remove(p) {
            if (!window.confirm('¿Eliminar esta foto?')) return;
            try { await axios.delete(`${props.baseUrl}/api/fotos/${p.id}`); emit('reload'); emit('toast', { message: 'Foto eliminada.', type: 'success' }); }
            catch (e) { emit('toast', { message: 'No se pudo eliminar la foto.', type: 'error' }); }
        }

        return { photoSlots, byType, extras, photoUrl, onImgError, zoom, fileInput, pick, onPick, remove };
    },
};
</script>
