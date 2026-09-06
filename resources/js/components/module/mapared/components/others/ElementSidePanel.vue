<template>
    <transition name="element-side-panel-fade">
        <div
            v-if="sidePanelOpen && sidePanelNode"
            class="element-side-panel"
            :class="{ 'element-side-panel--dark': darkMode }"
        >
            <div class="element-side-panel__header">
                <q-icon
                    :name="sidePanelNode.icon || 'mdi-help-circle-outline'"
                    :style="{ color: sidePanelNode.color }"
                    size="22px"
                />
                <div class="element-side-panel__title">
                    {{ sidePanelNode.text_node }}
                </div>
                <q-btn icon="close" flat round dense @click="close" />
            </div>

            <q-separator />

            <div class="element-side-panel__body">
                <div class="element-side-panel__section">
                    <div class="element-side-panel__label">
                        Identificación
                    </div>
                    <div>{{ sidePanelNode.text_node }}</div>
                    <div class="text-caption text-grey">
                        {{ typeLabel
                        }}<span v-if="sidePanelNode.id != null">
                            · ID {{ sidePanelNode.id }}</span
                        >
                    </div>
                </div>

                <div class="element-side-panel__section">
                    <div class="element-side-panel__label">Ubicación</div>
                    <div v-if="sidePanelNode.coords">
                        {{ sidePanelNode.coords.lat }},
                        {{ sidePanelNode.coords.lng }}
                    </div>
                    <div v-else class="text-grey">
                        Sin coordenadas asignadas
                    </div>
                </div>

                <div class="element-side-panel__section">
                    <div class="element-side-panel__label">Tipo</div>
                    <div>{{ classificationLabel }}</div>
                </div>

                <template v-if="sidePanelNode.coords">
                    <div class="element-side-panel__section">
                        <div class="element-side-panel__label">Puertos</div>
                        <div v-if="loadingResumen" class="text-caption text-grey">
                            Cargando…
                        </div>
                        <div v-else-if="resumen">
                            {{ resumen.puertos.ocupados }} ocupados /
                            {{ resumen.puertos.total }} total
                            <div class="text-caption text-grey">
                                {{ resumen.puertos.libres }} libres
                            </div>
                        </div>
                        <div v-else class="text-caption text-grey">
                            Sin datos disponibles
                        </div>
                    </div>

                    <div class="element-side-panel__section">
                        <div class="element-side-panel__label">Empalmes</div>
                        <div v-if="loadingResumen" class="text-caption text-grey">
                            Cargando…
                        </div>
                        <div v-else-if="resumen">
                            {{ resumen.empalmes.total }}
                        </div>
                        <div v-else class="text-caption text-grey">
                            Sin datos disponibles
                        </div>
                    </div>

                    <div class="element-side-panel__section">
                        <div class="element-side-panel__label">
                            Clientes colgados
                        </div>
                        <div v-if="loadingResumen" class="text-caption text-grey">
                            Cargando…
                        </div>
                        <div v-else-if="resumen">
                            {{ resumen.clientes.activos }} activos /
                            {{ resumen.clientes.total }} total
                        </div>
                        <div v-else class="text-caption text-grey">
                            Sin datos disponibles
                        </div>
                    </div>
                </template>

                <div
                    class="element-side-panel__section text-caption text-grey"
                >
                    <template v-if="sidePanelNode.coords">
                        Fotos e historial de cambios se incorporan en una fase
                        siguiente de esta ficha.
                    </template>
                    <template v-else>
                        Puertos, empalmes, clientes colgados, fotos e
                        historial de cambios aplican solo a elementos
                        ubicados en el mapa.
                    </template>
                </div>
            </div>

            <q-separator />

            <div class="element-side-panel__actions">
                <q-btn
                    v-if="canEdit"
                    no-caps
                    color="primary"
                    icon="edit"
                    label="Editar"
                    flat
                    @click="$emit('edit', sidePanelNode)"
                />
                <q-btn
                    v-if="sidePanelNode.coords"
                    no-caps
                    color="info"
                    icon="map"
                    label="Mostrar en el mapa"
                    flat
                    @click="$emit('show-on-map', sidePanelNode)"
                />
                <q-btn
                    v-if="canDelete"
                    no-caps
                    color="negative"
                    icon="delete"
                    label="Eliminar"
                    flat
                    @click="$emit('delete', sidePanelNode)"
                />
            </div>
        </div>
    </transition>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import {
    sidePanelNode,
    sidePanelOpen,
    closeElementSidePanel,
} from "../../../../../composables/useElementSidePanel";
import { darkMode } from "../../../../../hook/appConfig";
import { getLayerResumen } from "../../helper/layers-request";

defineOptions({
    name: "ElementSidePanel",
});

const props = defineProps({
    permissons: Object,
});

// Puertos/empalmes/clientes colgados (MR-23 fase 3, item #9990428): se
// consultan bajo demanda al seleccionar un elemento con coordenadas (los
// nodos de organización de árbol —carpetas/proyectos— no tienen puertos).
const resumen = ref(null);
const loadingResumen = ref(false);

watch(
    () => (sidePanelOpen.value ? sidePanelNode.value?.id : null),
    async (id) => {
        resumen.value = null;
        if (!id || !sidePanelNode.value?.coords) return;
        loadingResumen.value = true;
        resumen.value = await getLayerResumen(id);
        loadingResumen.value = false;
    },
    { immediate: true }
);

defineEmits(["edit", "delete", "show-on-map"]);

const DIALOG_LABELS = {
    folder: "Carpeta",
    project: "Proyecto",
    client: "Cliente",
    service_box: "Caja de servicio",
    junction_box: "Caja de empalme",
    site: "Sitio",
    rack: "Rack",
    cupboard: "Gabinete",
    olt: "OLT",
    switch: "Switch",
    splitter: "Splitter",
    router: "Router",
    organizer: "Organizador",
    route: "Ruta",
    zone: "Zona",
    pole: "Poste",
    building: "Edificio",
    note: "Nota",
    pack: "Paquete",
    source: "Fuente",
    region: "Región",
};

const typeLabel = computed(() => {
    const dialog = sidePanelNode.value?.dialog;
    return DIALOG_LABELS[dialog] ?? dialog ?? "Elemento";
});

const classificationLabel = computed(() => {
    const classification = sidePanelNode.value?.classification;
    if (classification === "project") return "Proyecto";
    if (classification === "network") return "Red";
    if (classification === "client") return "Cliente";
    return classification ?? "Sin clasificar";
});

const canEdit = computed(() => {
    if (!sidePanelNode.value) return false;
    const check = props.permissons?.data?.canView;
    return typeof check === "function"
        ? check(`maps_${sidePanelNode.value.dialog}_edit`)
        : true;
});

const canDelete = computed(() => {
    if (!sidePanelNode.value) return false;
    const check = props.permissons?.data?.canView;
    return typeof check === "function"
        ? check(`maps_${sidePanelNode.value.dialog}_remove`)
        : true;
});

const close = () => closeElementSidePanel();
</script>

<style scoped>
.element-side-panel {
    position: fixed;
    top: 0;
    right: 0;
    height: 100vh;
    width: 320px;
    max-width: 90vw;
    background: #fff;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
    z-index: 2000;
    display: flex;
    flex-direction: column;
}

.element-side-panel--dark {
    background: #1d1d1d;
    color: #fff;
}

.element-side-panel__header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 8px 12px 16px;
}

.element-side-panel__title {
    flex: 1;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.element-side-panel__body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
}

.element-side-panel__section {
    margin-bottom: 20px;
}

.element-side-panel__label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #9e9e9e;
    margin-bottom: 4px;
}

.element-side-panel__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 8px;
}

.element-side-panel-fade-enter-active,
.element-side-panel-fade-leave-active {
    transition: transform 0.18s ease;
}

.element-side-panel-fade-enter-from,
.element-side-panel-fade-leave-to {
    transform: translateX(100%);
}
</style>
