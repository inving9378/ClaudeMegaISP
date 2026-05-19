<template>
    <div class="ia-message" :class="`ia-${mensaje.rol}`">
        <div class="ia-bubble" :class="`ia-bubble-${mensaje.rol}`">
            <div
                v-if="mensaje.rol === 'assistant'"
                class="ia-md"
                ref="content"
                v-html="rendered"
            ></div>
            <div v-else class="ia-user-content">
                <div v-if="archivosVisibles.length" class="ia-user-archivos">
                    <template v-for="(a, i) in archivosVisibles" :key="`a-${i}`">
                        <a
                            v-if="a.es_imagen"
                            :href="a.url"
                            target="_blank"
                            rel="noopener"
                            class="ia-archivo-img"
                            :title="`${a.nombre_original || a.nombre || 'imagen'} (${formatBytes(a.tamanio ?? a.bytes)})`"
                        >
                            <img :src="a.url" />
                        </a>
                        <a
                            v-else
                            :href="a.url"
                            target="_blank"
                            rel="noopener"
                            class="ia-archivo-doc"
                            :title="`${a.nombre_original || a.nombre || 'documento'} (${formatBytes(a.tamanio ?? a.bytes)})`"
                        >
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                            <span>{{ a.nombre_original || a.nombre || 'documento.pdf' }}</span>
                        </a>
                    </template>
                </div>
                <div v-else-if="chipsLegacy.length" class="ia-user-imgs">
                    <div
                        v-for="(img, i) in chipsLegacy"
                        :key="`l-${i}`"
                        class="ia-img-chip"
                        :title="`${img.mime} (${formatBytes(img.bytes)})`"
                    >
                        <i class="bi bi-image"></i>
                        {{ img.nombre || `Imagen ${i + 1}` }}
                    </div>
                </div>
                <span class="ia-user-text">{{ mensaje.contenido }}</span>
            </div>

            <button
                v-if="mensaje.rol === 'assistant'"
                class="ia-copy-btn"
                @click="copiar"
                :title="copiado ? 'Copiado' : 'Copiar respuesta'"
            >
                <i :class="copiado ? 'bi bi-check2' : 'bi bi-clipboard'"></i>
            </button>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch, nextTick } from "vue";
import { renderMarkdown, bindCodeCopyButtons } from "../../../composables/useMarkdown.js";

export default {
    name: "IAMessageBubble",
    props: {
        mensaje: { type: Object, required: true },
    },
    setup(props) {
        const copiado = ref(false);
        const content = ref(null);

        const rendered = computed(() => renderMarkdown(props.mensaje.contenido));

        // Archivos persistidos en ia_message_files (fuente autoritativa).
        const archivosVisibles = computed(() => {
            const list = props.mensaje.archivos || [];
            return list.map((a) => ({
                ...a,
                es_imagen:
                    typeof a.es_imagen === "boolean"
                        ? a.es_imagen
                        : (a.tipo_mime || a.mime || "").startsWith("image/"),
            }));
        });

        // Chips legacy: mensajes viejos donde imagenes JSON estaba en ia_mensajes
        // pero no había registros en ia_message_files.
        const chipsLegacy = computed(() => {
            if (archivosVisibles.value.length) return [];
            return props.mensaje.imagenes || [];
        });

        const copiar = async () => {
            try {
                await navigator.clipboard.writeText(props.mensaje.contenido);
                copiado.value = true;
                setTimeout(() => (copiado.value = false), 2000);
            } catch (e) {
                console.error("Error copiando:", e);
            }
        };

        const formatBytes = (b) => {
            if (!b) return "";
            if (b < 1024) return `${b} B`;
            if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
            return `${(b / (1024 * 1024)).toFixed(1)} MB`;
        };

        const bind = () => {
            nextTick(() => bindCodeCopyButtons(content.value));
        };

        onMounted(bind);
        watch(() => props.mensaje.contenido, bind);

        return {
            rendered,
            copiado,
            copiar,
            content,
            formatBytes,
            archivosVisibles,
            chipsLegacy,
        };
    },
};
</script>

<style scoped>
.ia-message {
    display: flex;
    margin-bottom: 14px;
}
.ia-message.ia-user { justify-content: flex-end; }
.ia-message.ia-assistant { justify-content: flex-start; }
.ia-bubble {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 12px;
    position: relative;
}
.ia-bubble-user {
    background: #2563eb;
    color: #fff;
}
.ia-bubble-assistant {
    background: #fff;
    border: 1px solid #e5e7eb;
    color: #111;
}
.ia-user-content { white-space: pre-wrap; }
.ia-user-imgs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
.ia-img-chip {
    background: rgba(255,255,255,0.2);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
}
.ia-user-archivos {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.ia-archivo-img {
    display: block;
    width: 120px;
    height: 120px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.1);
}
.ia-archivo-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.ia-archivo-doc {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 8px;
    background: rgba(220, 38, 38, 0.15);
    color: #fff;
    font-size: 0.85em;
    text-decoration: none;
    border: 1px solid rgba(255, 255, 255, 0.25);
}
.ia-archivo-doc i {
    color: #fca5a5;
    font-size: 1.1em;
}
.ia-archivo-doc:hover {
    background: rgba(220, 38, 38, 0.3);
}

.ia-copy-btn {
    position: absolute;
    top: -10px;
    right: -10px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.ia-bubble:hover .ia-copy-btn { display: inline-flex; }
.ia-copy-btn i { font-size: 0.9em; }

/* Dark mode */
body[data-layout-mode="dark"] .ia-bubble-assistant {
    background: #2d3036;
    border-color: #3a3d44;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-copy-btn {
    background: #25282d;
    border-color: #3a3d44;
    color: #e5e7eb;
}
</style>
