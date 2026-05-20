<template>
    <div
        class="ia-input-area"
        :class="{ dragging }"
        @dragover.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
        @drop.prevent="onDrop"
        @paste="onPaste"
    >
        <div v-if="imagenes.length" class="ia-img-previews">
            <div v-for="(img, i) in imagenes" :key="i" class="ia-img-preview">
                <img :src="`data:${img.mime};base64,${img.data}`" />
                <button class="ia-img-remove" @click="quitarImagen(i)">×</button>
            </div>
        </div>

        <div class="ia-input-row">
            <button
                v-if="soportaImagenes"
                class="btn btn-light btn-sm"
                @click="$refs.fileInput.click()"
                title="Adjuntar imagen"
            >
                <i class="bi bi-image"></i>
            </button>
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                multiple
                hidden
                @change="onFiles"
            />
            <textarea
                v-model="texto"
                class="form-control ia-textarea"
                rows="1"
                placeholder="Escribe un mensaje..."
                :disabled="disabled"
                @keydown.enter.prevent.exact="enviar"
                @keydown.shift.enter.exact.stop="texto += '\n'"
            ></textarea>
            <button
                class="btn btn-primary"
                :disabled="!puedeEnviar"
                @click="enviar"
            >
                <i class="bi bi-send"></i>
            </button>
        </div>
        <small v-if="error" class="text-danger d-block mt-1">{{ error }}</small>
    </div>
</template>

<script>
import { ref, computed } from "vue";

const MAX_BYTES = 5 * 1024 * 1024;
const MAX_IMAGENES = 20;
const VALIDOS = ["image/jpeg", "image/png", "image/gif", "image/webp"];

export default {
    name: "IAMessageInput",
    props: {
        soportaImagenes: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
    },
    emits: ["enviar"],
    setup(props, { emit }) {
        const texto = ref("");
        const imagenes = ref([]);
        const dragging = ref(false);
        const error = ref("");

        const puedeEnviar = computed(
            () => !props.disabled && (texto.value.trim() || imagenes.value.length)
        );

        const fileToBase64 = (file) =>
            new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const result = reader.result;
                    const b64 = result.split(",")[1];
                    resolve(b64);
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });

        const aceptarArchivo = async (file) => {
            if (!VALIDOS.includes(file.type)) {
                error.value = `Formato no soportado: ${file.type}`;
                return;
            }
            if (file.size > MAX_BYTES) {
                error.value = `Imagen demasiado grande (${(file.size / 1024 / 1024).toFixed(1)} MB). Máximo 5 MB.`;
                return;
            }
            if (imagenes.value.length >= MAX_IMAGENES) {
                error.value = `Máximo ${MAX_IMAGENES} imágenes por mensaje.`;
                return;
            }
            error.value = "";
            const data = await fileToBase64(file);
            imagenes.value.push({ mime: file.type, data });
        };

        const onFiles = async (e) => {
            for (const f of e.target.files) await aceptarArchivo(f);
            e.target.value = "";
        };

        const onPaste = async (e) => {
            if (!props.soportaImagenes) return;
            const items = e.clipboardData?.items || [];
            for (const item of items) {
                if (item.kind === "file") {
                    const f = item.getAsFile();
                    if (f) await aceptarArchivo(f);
                }
            }
        };

        const onDragOver = () => (dragging.value = true);
        const onDragLeave = () => (dragging.value = false);
        const onDrop = async (e) => {
            dragging.value = false;
            if (!props.soportaImagenes) return;
            for (const f of e.dataTransfer.files || []) {
                await aceptarArchivo(f);
            }
        };

        const quitarImagen = (i) => imagenes.value.splice(i, 1);

        const enviar = () => {
            if (!puedeEnviar.value) return;
            emit("enviar", {
                texto: texto.value.trim(),
                imagenes: imagenes.value.map(({ mime, data }) => ({ mime, data })),
            });
            texto.value = "";
            imagenes.value = [];
        };

        return {
            texto,
            imagenes,
            dragging,
            error,
            puedeEnviar,
            onFiles,
            onPaste,
            onDragOver,
            onDragLeave,
            onDrop,
            quitarImagen,
            enviar,
        };
    },
};
</script>

<style scoped>
.ia-input-area {
    border-top: 1px solid #e5e7eb;
    padding: 12px;
    background: #fff;
    transition: background 0.2s;
}
.ia-input-area.dragging {
    background: #eef2ff;
    border-top: 2px dashed #6366f1;
}
.ia-input-row {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
.ia-textarea {
    resize: vertical;
    min-height: 40px;
    max-height: 200px;
}
.ia-img-previews {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.ia-img-preview {
    position: relative;
    width: 80px;
    height: 80px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
}
.ia-img-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.ia-img-remove {
    position: absolute;
    top: 2px;
    right: 2px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    border: 0;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    line-height: 18px;
    cursor: pointer;
}

/* Dark mode */
body[data-layout-mode="dark"] .ia-input-area {
    background: #25282d;
    border-top-color: #3a3d44;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-input-area.dragging {
    background: #2d3036;
    border-top-color: #818cf8;
}
body[data-layout-mode="dark"] .ia-input-area textarea.form-control {
    background-color: #2d3036;
    border-color: #3a3d44;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-img-preview {
    border-color: #3a3d44;
}
</style>
