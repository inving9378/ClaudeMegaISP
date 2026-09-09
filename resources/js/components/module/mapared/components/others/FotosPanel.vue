<template>
    <div class="fotos-panel">
        <div v-if="loading" class="text-caption text-grey">Cargando…</div>

        <template v-else>
            <div v-if="fotos && fotos.length" class="fotos-panel__grid">
                <div
                    v-for="foto in fotos"
                    :key="foto.id"
                    class="fotos-panel__thumb"
                    @click="verFoto(foto)"
                >
                    <img
                        :src="foto.thumb_url || foto.url"
                        :alt="foto.caption || 'Foto'"
                    />
                    <q-btn
                        v-if="canEliminar"
                        icon="delete"
                        flat
                        round
                        dense
                        size="sm"
                        color="negative"
                        class="fotos-panel__thumb-delete"
                        @click.stop="confirmarEliminar(foto)"
                    />
                </div>
            </div>
            <div v-else class="text-caption text-grey q-mb-sm">
                Sin fotos registradas en este elemento.
            </div>

            <input
                v-if="canSubir"
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                style="display: none"
                @change="onFileSelected"
            />
            <q-btn
                v-if="canSubir"
                no-caps
                flat
                dense
                icon="add_a_photo"
                color="primary"
                label="Subir foto"
                :loading="subiendo"
                @click="fileInput?.click()"
            />
        </template>

        <q-dialog v-model="dialogoAbierto">
            <q-img
                v-if="fotoSeleccionada"
                :src="fotoSeleccionada.url"
                style="max-width: 90vw; max-height: 90vh"
            />
        </q-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Swal from "sweetalert2";
import { message } from "../../../../../helpers/toastMsg";
import { getFotos, subirFoto, eliminarFoto } from "../../helper/fotos-request";

defineOptions({
    name: "FotosPanel",
});

const props = defineProps({
    tipo: {
        type: String,
        required: true,
    },
    id: {
        type: [Number, String],
        required: true,
    },
    canSubir: {
        type: Boolean,
        default: false,
    },
    canEliminar: {
        type: Boolean,
        default: false,
    },
});

// Mismo límite que valida el backend (FotosController::store, max:5120 KB) — se
// rechaza antes de enviar para no gastar el viaje de red en algo que va a fallar igual.
const MAX_SIZE = 5 * 1024 * 1024;

const fotos = ref(null);
const loading = ref(false);
const subiendo = ref(false);
const fileInput = ref(null);
const dialogoAbierto = ref(false);
const fotoSeleccionada = ref(null);

const cargar = async () => {
    loading.value = true;
    fotos.value = await getFotos(props.tipo, props.id);
    loading.value = false;
};

onMounted(cargar);

const verFoto = (foto) => {
    fotoSeleccionada.value = foto;
    dialogoAbierto.value = true;
};

const onFileSelected = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;

    if (file.size > MAX_SIZE) {
        message("La foto no puede pesar más de 5MB.", "error");
        return;
    }

    subiendo.value = true;
    const respuesta = await subirFoto(props.tipo, props.id, file);
    subiendo.value = false;

    if (respuesta.ok) {
        message("Foto subida correctamente.");
        cargar();
    } else {
        message(respuesta.message, "error");
    }
};

const confirmarEliminar = (foto) => {
    Swal.fire({
        title: "¿Eliminar esta foto?",
        text: "Esta acción no se puede deshacer.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const respuesta = await eliminarFoto(foto.id);
        if (respuesta.ok) {
            message("Foto eliminada correctamente.");
            cargar();
        } else {
            message(respuesta.message, "error");
        }
    });
};
</script>

<style scoped>
.fotos-panel__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    margin-bottom: 8px;
}

.fotos-panel__thumb {
    position: relative;
    aspect-ratio: 1 / 1;
    cursor: pointer;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid rgba(128, 128, 128, 0.25);
}

.fotos-panel__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.fotos-panel__thumb-delete {
    position: absolute;
    top: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.85);
}
</style>
