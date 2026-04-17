<template>
    <q-item class="q-py-md">
        <q-item-section />
        <q-item-section avatar class="q-ml-sm">
            <q-btn
                icon="mdi-sync"
                round
                color="primary"
                size="sm"
                padding="5px"
                :loading="loading"
                @click="loadData"
                ><q-tooltip class="bg-primary" :offset="[10, 10]">
                    Actualizar
                </q-tooltip></q-btn
            >
        </q-item-section>
        <q-item-section
            avatar
            class="q-ml-sm"
            v-if="permissons.data.canView(`maps_folder_edit`)"
        >
            <q-btn
                icon="mdi-map-marker-multiple-outline"
                size="sm"
                round
                color="primary"
                :disable="loading || hasLayerEdit || tickedNodes.length === 0"
                ><q-tooltip
                    class="bg-primary"
                    :offset="[10, 10]"
                    v-if="!loading && !hasLayerEdit"
                >
                    Convertir a
                </q-tooltip>
                <q-menu
                    transition-show="scale"
                    transition-hide="scale"
                    :style="{
                        'min-height': `${
                            convertOptions.filter((m) =>
                                permissons.data.canView(`maps_${m.dialog}_edit`)
                            ).length * 30
                        }px !important`,
                    }"
                >
                    <q-list dense style="min-width: 100px">
                        <q-item
                            v-for="(item, indexItem) in convertOptions.filter(
                                (m) =>
                                    permissons.data.canView(
                                        `maps_${m.dialog}_edit`
                                    )
                            )"
                            :key="`convert-opt-${indexItem}`"
                            clickable
                            @click="convertTickeds(item)"
                        >
                            <q-item-section avatar
                                ><q-icon size="xs" :name="item.icon"
                            /></q-item-section>
                            <q-item-section class="q-ml-xs">{{
                                item.text
                            }}</q-item-section>
                        </q-item>
                    </q-list>
                </q-menu>
            </q-btn>
        </q-item-section>
        <q-item-section avatar class="q-ml-sm">
            <q-btn
                :icon="expanded ? 'mdi-collapse-all' : 'mdi-expand-all'"
                round
                color="primary"
                size="sm"
                padding="5px"
                :disable="loading"
                @click="toggleTree"
                ><q-tooltip
                    class="bg-primary"
                    :offset="[10, 10]"
                    v-if="!loading"
                >
                    {{ expanded ? "Contraer todo" : "Expandir todo" }}
                </q-tooltip></q-btn
            >
        </q-item-section>
        <q-item-section
            avatar
            class="q-ml-sm"
            v-if="permissons.data.canView(`maps_kmz_load`)"
        >
            <q-btn
                icon="mdi-file-upload-outline"
                round
                color="primary"
                size="sm"
                padding="5px"
                :disable="loading || hasLayerEdit || !nodeUploadKmz"
                @click="
                    () => {
                        kmzFile.pickFiles();
                    }
                "
                ><q-tooltip
                    class="bg-primary"
                    :offset="[10, 10]"
                    v-if="!loading && !hasLayerEdit && !nodeUploadKmz"
                >
                    Cargar kmz
                </q-tooltip></q-btn
            >
        </q-item-section>
    </q-item>
    <q-item dense style="padding: 0">
        <q-input
            ref="filterRef"
            dense
            filled
            v-model="filter"
            placeholder="Filtrar..."
            :dark="darkMode"
            clearable
            @update:model-value="(val) => setToLocalStorage('filter-tree', val)"
        />
    </q-item>

    <q-virtual-scroll
        style="max-height: 76vh"
        :items="treeData"
        separator
        v-slot="{ item }"
    >
        <q-tree
            ref="treeRef"
            :nodes="[item]"
            :dark="darkMode"
            :filter="filter"
            node-key="key"
            label-key="text_node"
            tick-strategy="leaf"
            no-nodes-label="No se encontraron coincidencias"
            no-results-label="No existen proyectos"
            selected-color="primary"
            v-model:selected="selected"
            v-model:ticked="tickedNodes"
            v-model:expanded="expandedNodes"
            no-transition
            @update:selected="onSelectedNode"
            @update:expanded="(val) => setToLocalStorage('expanded-nodes', val)"
        >
            <template v-slot:default-header="prop">
                <q-item
                    dense
                    style="padding: 0"
                    :id="`node-${prop.node.key}`"
                    draggable="true"
                    @dragstart="onDragStart($event, prop.node)"
                    @dragover.prevent="onDragOver($event, prop.node)"
                    @dragenter.prevent
                    @drop="onDrop($event, prop.node)"
                    @dragend="onDragEnd"
                    class="node-wrapper"
                    :class="{
                        'is-dragging':
                            isDragging && draggedNode?.id === prop.node.id,
                    }"
                >
                    <div
                        v-if="isDropTarget(prop.node, 'before')"
                        class="drop-indicator drop-indicator-before"
                    ></div>
                    <q-item-section
                        avatar
                        style="margin-left: -20px"
                        v-if="isDraggable(prop.node)"
                    >
                        <q-icon
                            name="drag_indicator"
                            class="cursor-grab q-px-sm"
                            style="opacity: 0.5"
                        />
                    </q-item-section>
                    <q-item-section avatar>
                        <q-icon
                            :name="prop.node.icon"
                            :style="{ color: prop.node.color }"
                        />
                    </q-item-section>
                    <q-item-section>
                        <q-item-label lines="1" class="q-ml-sm">
                            {{ prop.node.name }}
                        </q-item-label>
                    </q-item-section>
                    <q-item-section
                        avatar
                        side
                        style="padding-left: 2px !important"
                        v-if="
                            prop.node.parent_key !== 'root-node' &&
                            prop.node.classification === 'project' &&
                            permissons.data.canView(
                                `maps_${prop.node.dialog}_edit`
                            )
                        "
                    >
                        <q-btn
                            icon="edit"
                            size="sm"
                            flat
                            dense
                            color="primary"
                            :disable="loading || hasLayerEdit"
                            @click.stop="editNode(prop.node)"
                            ><q-tooltip
                                class="bg-primary"
                                :offset="[10, 10]"
                                v-if="!loading && !hasLayerEdit"
                            >
                                Editar
                                {{
                                    prop.node.text?.toLowerCase() ?? "proyecto"
                                }}
                            </q-tooltip></q-btn
                        >
                    </q-item-section>
                    <q-item-section
                        avatar
                        style="padding-left: 2px !important"
                        v-if="
                            permissons.data.canView(`maps_folder_edit`) &&
                            prop.node.classification === 'project' &&
                            prop.node.id !== null
                        "
                    >
                        <q-btn
                            icon="mdi-map-marker-multiple-outline"
                            size="sm"
                            flat
                            dense
                            color="primary"
                            :disable="
                                loading ||
                                hasLayerEdit ||
                                (prop.node.is_layer &&
                                    prop.node.type !== 'marker')
                            "
                            ><q-tooltip
                                class="bg-primary"
                                :offset="[10, 10]"
                                v-if="
                                    !loading &&
                                    !hasLayerEdit &&
                                    prop.node.is_layer &&
                                    prop.node.type === 'marker'
                                "
                            >
                                Convertir a
                            </q-tooltip>
                            <q-menu
                                transition-show="scale"
                                transition-hide="scale"
                                :style="{
                                    'min-height': `${
                                        convertOptions.filter((m) =>
                                            permissons.data.canView(
                                                `maps_${m.dialog}_edit`
                                            )
                                        ).length * 30
                                    }px !important`,
                                }"
                            >
                                <q-list dense style="min-width: 100px">
                                    <q-item
                                        v-for="(
                                            item, indexItem
                                        ) in convertOptions.filter((m) =>
                                            permissons.data.canView(
                                                `maps_${m.dialog}_edit`
                                            )
                                        )"
                                        :key="`convert-opt-${indexItem}`"
                                        clickable
                                        @click="convertObject(prop.node, item)"
                                    >
                                        <q-item-section avatar
                                            ><q-icon
                                                size="xs"
                                                :name="item.icon"
                                        /></q-item-section>
                                        <q-item-section class="q-ml-xs">{{
                                            item.text
                                        }}</q-item-section>
                                    </q-item>
                                </q-list>
                            </q-menu>
                        </q-btn>
                    </q-item-section>
                    <q-item-section
                        avatar
                        side
                        style="padding-left: 2px !important"
                        v-if="
                            prop.node.parent_key !== 'root-node' &&
                            prop.node.classification === 'project' &&
                            permissons.data.canView(
                                `maps_${prop.node.dialog}_remove`
                            )
                        "
                    >
                        <q-btn
                            icon="delete"
                            size="sm"
                            flat
                            dense
                            color="negative"
                            :loading="loading && keyId === prop.node.key"
                            :disable="loading || hasLayerEdit"
                            @click.stop="deleteObject(prop.node)"
                            ><q-tooltip
                                class="bg-negative"
                                :offset="[10, 10]"
                                v-if="!loading && !hasLayerEdit"
                            >
                                Eliminar
                                {{
                                    prop.node.text?.toLowerCase() ?? "proyecto"
                                }}
                            </q-tooltip></q-btn
                        >
                    </q-item-section>
                    <q-item-section
                        avatar
                        side
                        style="padding-left: 2px !important"
                        v-if="
                            (prop.node.classification === 'project' ||
                                prop.node.classification === 'network') &&
                            permissons.data.canView(
                                `maps_change_classification`
                            ) &&
                            prop.node.id !== null
                        "
                    >
                        <q-btn
                            :icon="
                                prop.node.classification === 'project'
                                    ? 'mdi-map-marker-up'
                                    : 'mdi-map-marker-down'
                            "
                            size="sm"
                            flat
                            dense
                            color="primary"
                            :disable="loading || hasLayerEdit"
                            @click.stop="changeClassification(prop.node)"
                            ><q-tooltip
                                class="bg-primary"
                                :offset="[10, 10]"
                                v-if="!loading && !hasLayerEdit"
                            >
                                Pasar a
                                {{
                                    prop.node.classification === "project"
                                        ? "red"
                                        : "proyectos"
                                }}
                            </q-tooltip></q-btn
                        >
                    </q-item-section>
                    <q-item-section
                        avatar
                        side
                        style="padding-left: 2px !important"
                        v-if="
                            prop.node.key !== 'root-node' &&
                            !prop.node.coords &&
                            prop.node.classification === 'project'
                        "
                    >
                        <q-btn
                            icon="add"
                            size="sm"
                            flat
                            dense
                            color="positive"
                            :disable="loading || hasLayerEdit"
                            @click.stop="(ev) => ev.preventDefault()"
                        >
                            <q-tooltip
                                class="bg-positive"
                                :offset="[10, 10]"
                                v-if="!loading && !hasLayerEdit"
                            >
                                Adicionar componente
                            </q-tooltip>
                            <q-menu
                                transition-show="scale"
                                transition-hide="scale"
                                :style="{
                                    'min-height': `${
                                        menuOptions.filter((m) =>
                                            permissons.data.canView(
                                                `maps_${m.dialog}_add`
                                            )
                                        ).length * 30
                                    }px !important`,
                                }"
                            >
                                <q-list dense style="min-width: 100px">
                                    <q-item
                                        v-for="(
                                            item, indexItem
                                        ) in menuOptions.filter((m) =>
                                            permissons.data.canView(
                                                `maps_${m.dialog}_add`
                                            )
                                        )"
                                        :key="`menu-opt-${indexItem}`"
                                        clickable
                                        @click="onNewComponent(prop.node, item)"
                                    >
                                        <q-item-section avatar
                                            ><q-icon
                                                size="xs"
                                                :name="item.icon"
                                        /></q-item-section>
                                        <q-item-section class="q-ml-xs">{{
                                            item.text
                                        }}</q-item-section>
                                    </q-item>
                                </q-list>
                            </q-menu>
                        </q-btn>
                    </q-item-section>
                    <q-item-section
                        avatar
                        side
                        style="padding-left: 2px !important"
                        v-if="prop.node.coords"
                    >
                        <q-btn
                            icon="map"
                            size="sm"
                            flat
                            dense
                            color="info"
                            :disable="loading || hasLayerEdit"
                            @click.stop="showOnMap(prop.node)"
                        >
                            <q-tooltip
                                class="bg-info"
                                :offset="[10, 10]"
                                v-if="!loading && !hasLayerEdit"
                            >
                                Mostrar en el mapa
                            </q-tooltip>
                        </q-btn>
                    </q-item-section>
                    <div
                        v-if="isDropTarget(prop.node, 'child')"
                        class="drop-indicator drop-indicator-child"
                    ></div>
                    <div
                        v-if="isDropTarget(prop.node, 'after')"
                        class="drop-indicator drop-indicator-after"
                    ></div>
                </q-item>
            </template>
        </q-tree>
    </q-virtual-scroll>

    <q-dialog
        v-model="dialog"
        @hide="project = null"
        @before-show="onShowDialog"
    >
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ project ? "Editar" : "Nuevo" }}
                            {{ !isProject(project) ? "carpeta" : "proyecto" }}
                        </h6></q-item-section
                    >
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="dialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <label for="object-name">Nombre</label>
                <q-input v-model="formData.name" outlined dense />
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
                <q-btn
                    no-caps
                    color="primary"
                    label="Guardar"
                    :loading="loading"
                    @click="save"
                />
                <q-btn
                    no-caps
                    flat
                    color="primary"
                    label="Cancelar"
                    @click="dialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <q-file
        ref="kmzFile"
        accept=".kmz, .kml"
        class="hidden"
        @rejected="onRejectedKMZ"
        @update:model-value="onSelectKMZ"
    />
</template>

<script setup>
import { ref, watch, computed, nextTick, onMounted } from "vue";
import {
    getProjects,
    saveProject,
    destroyObject,
    convertToNetwork,
    loadKMZ,
    moveNode as moveNodeDB,
    convertFromProject,
    convertFromLayer,
    convertFromTickeds,
} from "../helper/request";
import { message } from "../../../../helpers/toastMsg";
import { menuOptions, hasLayerEdit, currentMarker } from "../helper/mapUtils";
import Swal from "sweetalert2";
import { darkMode } from "../../../../hook/appConfig";
import { isFullScreen } from "../../../../composables/useFullScreen";
import {
    nodeMap,
    deleteNode,
    getNodeByKey,
    addNode,
    treeData,
    setNodes,
    tickedNodes,
    expandedNodes,
    currentNode,
} from "../../../../composables/useNodeMap";

import {
    getFromLocalStorage,
    setToLocalStorage,
} from "../../../../composables/useLocalStorage";

defineOptions({
    name: "ProjectsComponent",
});

const props = defineProps({
    reload: Boolean,
    removedObject: Object,
    permissons: Object,
    width: Number,
});

const emits = defineEmits([
    "selected",
    "new-component",
    "edit-component",
    "destroy-component",
    "loaded",
    "draw-layers",
    "show-on-map",
]);

const excludes = [
    "folder",
    "region",
    "route",
    "note",
    "elements_in_serie",
    "client",
];

const kmzFile = ref(null);
const treeRef = ref(null);
const dialog = ref(false);
const loading = ref(false);
const project = ref(null);
const keyId = ref(null);
const formData = ref({
    name: null,
});
const selected = ref(null);

const expanded = ref(true);
const filter = ref("");
const filterRef = ref(null);
const projectNode = ref(null);
const draggedNode = ref(null);
const nodeUploadKmz = ref(null);
const convertOptions = ref([]);

const dropTargetNode = ref(null);
const dropPosition = ref(null);
const isDragging = ref(false);

onMounted(() => {
    convertOptions.value = menuOptions.filter(
        (m) => !excludes.includes(m.dialog)
    );

    filter.value = getFromLocalStorage("filter-tree");
    expandedNodes.value = getFromLocalStorage("expanded-nodes") ?? [
        "root-node",
    ];
});

watch(
    () => props.reload,
    (n) => {
        if (n) {
            loadData();
        }
    }
);

watch(selected, (n) => {
    if (n) {
        const node = nodeMap.value[n];
        if (node.classification === "project") {
            nodeUploadKmz.value = node;
        } else {
            nodeUploadKmz.value = null;
        }
    } else {
        nodeUploadKmz.value = null;
    }
});

watch(
    currentNode,
    (n) => {
        if (n) {
            const node = getNodeByKey(n.key);
            if (node) {
                Object.assign(node, n);
            } else {
                addNode(n);
            }
            if (n.coords) {
                treeRef.value?.setTicked(n.key, true);
            }
        }
    },
    { deep: true }
);

watch(currentMarker, async (n) => {
    if (n) {
        let keys = await getParentNodes(n);
        keys.forEach((k) => {
            if (!expandedNodes.value.includes(k)) {
                expandedNodes.value.push(k);
            }
        });
        currentMarker.value = null;
        nextTick(() => {
            const el = document.getElementById(`node-${n.key}`);
            el.scrollIntoView({
                behavior: "smooth",
                block: "center",
            });
            const item = el.closest(".q-tree__node-header");
            item.classList.add("highlighted-node");
            setTimeout(() => {
                item.classList.remove("highlighted-node");
            }, 2000);
        });
    }
});

watch(
    () => props.removedObject,
    (n) => {
        if (n) {
            deleteNode(n.key);
        }
    }
);

watch(
    tickedNodes,
    (selected, noSelected) => {
        let selectedLayers = [];
        noSelected = noSelected.filter((s) => !selected.includes(s));
        selected.forEach((n) => {
            let node = nodeMap.value[n];
            if (node) {
                selectedLayers.push(node);
            }
        });
        setToLocalStorage("tickeds-nodes", selected);
        emits("draw-layers", selectedLayers, noSelected);
    },
    {
        deep: true,
    }
);

const getParentNodes = (n, keys = []) => {
    let k = n?.parent_key;
    if (k) {
        keys.push(k);
        const node = nodeMap.value[k];
        getParentNodes(node, keys);
    }
    return keys;
};

const loadData = async () => {
    loading.value = true;
    const nodes = await getProjects();
    setNodes(nodes);
    loading.value = false;
    emits("loaded", nodes);
};

const onSelectedNode = async (key) => {
    let node = nodeMap.value[key];
    if (node && node.classification === "project" && !node.coords) {
        projectNode.value = node;
        emits("selected", node);
    } else {
        projectNode.value = null;
        selected.value = null;
        emits("selected", null);
    }
};

const isProject = (node) => {
    const parent = nodeMap.value[node.parent_key];
    return parent && parent.parent_id === null ? true : false;
};

const isDraggable = (node) => {
    return node.classification === "project" && node.key !== "project-root";
};

const onDragStart = (event, node) => {
    if (!isDraggable(node)) {
        dropTargetNode.value = null;
        dropPosition.value = null;
        event.dataTransfer.dropEffect = "none";
        return;
    }
    isDragging.value = true;
    draggedNode.value = node;
    event.dataTransfer.setData("text/plain", node.key);
    event.dataTransfer.effectAllowed = "move";
};

const onDragOver = (event, node) => {
    event.preventDefault();

    const rect = event.currentTarget.getBoundingClientRect();
    const quarterY = rect.top + rect.height / 4;
    const threeQuarterY = rect.top + (3 * rect.height) / 4;

    if (event.clientY < quarterY) {
        dropPosition.value = "before";
    } else if (event.clientY > threeQuarterY) {
        dropPosition.value = "after";
    } else {
        dropPosition.value = "child";
    }

    if (
        !draggedNode.value ||
        !isValidDrop(node) ||
        (draggedNode.value && node.key === draggedNode.value.key) ||
        isDescendant(draggedNode.value, node) ||
        (node?.is_layer && dropPosition.value === "child")
    ) {
        dropTargetNode.value = null;
        dropPosition.value = null;
        event.dataTransfer.dropEffect = "none";
        return;
    }

    dropTargetNode.value = node;
    event.dataTransfer.dropEffect = "move";
};

const onDrop = (event, targetNode) => {
    event.preventDefault();
    const nodeToMove = draggedNode.value;
    if (
        !nodeToMove ||
        !isValidDrop(targetNode) ||
        targetNode.key === nodeToMove.key ||
        isDescendant(nodeToMove, targetNode) ||
        (targetNode?.is_layer && dropPosition.value === "child")
    ) {
        message("Destino no válido.", "error");
        return;
    }

    removeNodeFromTree(nodeToMove.key, treeData.value);

    let nodes = [];
    let parent = null;
    if (dropPosition.value === "child") {
        if (!targetNode.children) {
            targetNode.children = [];
        }
        targetNode.children.push(nodeToMove);
        nodes = targetNode.children;
        parent = targetNode;
    } else {
        const targetParent = targetNode?.parent_key
            ? treeRef.value.getNodeByKey(targetNode.parent_key)
            : treeData.value[0].children[1];
        const siblings = targetParent.children;
        const targetIndex = siblings.findIndex((n) => n.key === targetNode.key);
        const insertIndex =
            dropPosition.value === "before" ? targetIndex : targetIndex + 1;
        siblings.splice(insertIndex, 0, nodeToMove);
        nodes = siblings;
        parent = targetParent;
    }

    let positionsFolders = [];
    let positionsLayers = [];
    for (let i = 0; i < nodes.length; i++) {
        const n = nodes[i];
        if (n.coords) {
            positionsLayers.push({
                id: n.id,
                level: i,
            });
        } else {
            positionsFolders.push({
                id: n.id,
                level: i,
            });
        }
    }
    moveNode(nodeToMove, parent, {
        layers: positionsLayers,
        folders: positionsFolders,
    });

    treeData.value = [...treeData.value];
};

const onDragEnd = () => {
    isDragging.value = false;
    draggedNode.value = null;
    dropTargetNode.value = null;
    dropPosition.value = null;
};

const removeNodeById = (nodes, key) => {
    for (let i = 0; i < nodes.length; i++) {
        if (nodes[i].key === key) {
            nodes.splice(i, 1);
            return true;
        }
        if (nodes[i].children && removeNodeById(nodes[i].children, key)) {
            return true;
        }
    }
    return false;
};

const findParentNode = (nodes, key, parent = null) => {
    for (const node of nodes) {
        if (node.key === key) return parent;
        if (node.children) {
            const found = findParentNode(node.children, key, node);
            if (found) return found;
        }
    }
    return null;
};

const isDescendant = (potentialParent, potentialChild) => {
    if (!potentialParent.children) return false;
    for (const child of potentialParent.children) {
        if (child.key === potentialChild.key) return true;
        if (isDescendant(child, potentialChild)) return true;
    }
    return false;
};

const isValidDrop = (node) => {
    return (
        draggedNode.value && node.classification === "project",
        draggedNode.value.key !== node.key &&
            draggedNode.value.parent_key !== node.key
    );
};

const moveNode = async (sourceNode, targetNode, positions) => {
    const data = await moveNodeDB(
        sourceNode.id,
        targetNode?.id ?? null,
        sourceNode.coords ? "marker" : "folder",
        positions
    );
    if (data) {
        message("Objeto sincronizado correctamente");
        setNodes(data);
    } else {
        message("Error al mover el objeto", "error");
    }
};

const removeNodeFromTree = (key, nodes) => {
    for (let i = 0; i < nodes.length; i++) {
        if (nodes[i].key === key) {
            nodes.splice(i, 1);
            return true;
        }
        if (nodes[i].children && nodes[i].children.length > 0) {
            const found = removeNodeFromTree(key, nodes[i].children);
            if (found) return true;
        }
    }
    return false;
};

const editNode = (node) => {
    if (node.coords) {
        treeRef.value.setTicked([node.key], true);
        emits("edit-component", node);
    } else {
        project.value = node;
        dialog.value = true;
    }
};

const showOnMap = (node) => {
    treeRef.value.setTicked([node.key], true);
    emits("show-on-map", node);
};

const toggleTree = () => {
    expanded.value = !expanded.value;
    if (expanded.value) {
        treeRef.value.expandAll();
    } else {
        treeRef.value.collapseAll();
    }
};

const resetFilter = () => {
    filter.value = "";
    filterRef.value.focus();
};

const onShowDialog = () => {
    formData.value.name = project.value?.name ?? null;
};

const changeClassification = async (node) => {
    const data = await convertToNetwork({
        id: node.id,
        type: node.coords ? "MapLayer" : "MapProyect",
        classification:
            node.classification === "project" ? "network" : "project",
    });
    setNodes(data);
};

const onRejectedKMZ = () => {
    message("Fichero no permitido", "error");
};

const onSelectKMZ = async (val) => {
    loading.value = true;
    let data = await loadKMZ(nodeUploadKmz.value.id, val);
    loading.value = false;
    if (data && data.success) {
        const { tickeds, nodes } = data;
        setNodes(nodes);
        nextTick(() => {
            tickeds.forEach((key) => {
                const el = document.getElementById(`node-${key}`);
                const check = el
                    .closest(".q-tree__node-header")
                    .getElementsByTagName("input")[0];
                if (check) {
                    check.click();
                }
            });
        });
    } else {
        message("Error al cargar el KMZ", "error");
    }
};

const save = async () => {
    loading.value = true;
    const object = await saveProject(project.value?.id ?? null, formData.value);
    loading.value = false;
    if (object !== null) {
        currentNode.value = object;
        dialog.value = false;
        if (project.value.parent_id) {
            message(
                `Carpeta ${
                    project.value?.id ? "modificada" : "adicionada"
                } correctamente`,
                "success"
            );
        } else {
            message(
                `Proyecto ${
                    project.value?.id ? "modificado" : "adicionado"
                } correctamente`,
                "success"
            );
        }
    } else {
        message(
            "Ha ocurrido un error interno. Consulte al administrador",
            "error"
        );
    }
};

const deleteObject = (node) => {
    Swal.fire({
        title: "Confirmación!",
        text: `Seguro que deseas el/la ${
            node.text?.toLowerCase() ?? "proyecto"
        } ${node.text_node}?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Si",
        cancelButtonText: "No",
        target: isFullScreen.value ? "#map" : "body",
    }).then(async (result) => {
        if (result.isConfirmed) {
            keyId.value = node.key;
            loading.value = true;
            let object = await destroyObject(node);
            loading.value = false;
            if (object) {
                if (object.nodes) {
                    setNodes(object.nodes);
                } else {
                    deleteNode(node.key);
                }
                emits("destroy-component", node);
                message("Objeto eliminado correctamente", "success");
            } else {
                message("No se ha podido eliminar este objeto", "error");
            }
        }
    });
};

const onNewComponent = (project, type) => {
    selected.value = project.key;
    emits("new-component", project, type);
};

const convertObject = async (node, to) => {
    loading.value = true;
    let result = null;
    if (node.is_layer) {
        result = await convertFromLayer(node.id, to);
    } else {
        result = await convertFromProject(node.id, to);
    }
    loading.value = false;
    if (result) {
        result.forEach((node) => {
            const found = getNodeByKey(node.key);
            if (found) {
                Object.assign(found, node);
            }
        });
        nextTick(() => {
            updateMarkers();
        });
        message("Objeto(s) convertido(s) correctamente", "success");
    } else {
        message(
            "Ha ocurrido un error interno. Consulte al administrador",
            "error"
        );
    }
};

const getIdsFromNode = (node, ids = []) => {
    for (let i = 0; i < node.children.length; i++) {
        const n = node.children[i];
        if (n.is_layer) {
            if (n.dialog === "kmz") {
                ids.push(n.id);
            }
        } else if (n.children?.length > 0) {
            getIdsFromNode(n, ids);
        }
    }
    return ids;
};

const convertTickeds = async (to) => {
    let ids = [];
    for (let i = 0; i < tickedNodes.value.length; i++) {
        let node = nodeMap.value[tickedNodes.value[i]];
        if (node?.classification === "project" && node.type === "marker") {
            if (node.is_layer) {
                if (node.dialog === "kmz") {
                    ids.push(node.id);
                }
            } else if (node.children?.length > 0) {
                ids = getIdsFromNode(node, ids);
            }
        }
    }
    if (ids.length > 0) {
        loading.value = true;
        let result = await convertFromTickeds(ids, to);
        loading.value = false;
        if (result) {
            result.forEach((node) => {
                const found = getNodeByKey(node.key);
                if (found) {
                    Object.assign(found, node);
                }
            });
            nextTick(() => {
                updateMarkers();
            });
            message("Objeto(s) convertido(s) correctamente", "success");
        } else {
            message(
                "Ha ocurrido un error interno. Consulte al administrador",
                "error"
            );
        }
    } else {
        message("No se encontraron marcas en la selección actual", "info");
    }
};

const updateMarkers = () => {
    let selectedLayers = [];
    tickedNodes.value.forEach((n) => {
        let node = nodeMap.value[n];
        if (node) {
            selectedLayers.push(node);
        }
    });
    emits("draw-layers", selectedLayers, []);
};

const isDropTarget = (node, position) => {
    return (
        dropTargetNode.value?.id === node.id && dropPosition.value === position
    );
};
</script>

<style lang="scss" scope>
.q-field.row,
.q-field__control.row {
    margin-left: 0px !important;
    margin-right: 0px !important;
    --bs-gutter-x: 0px !important;
}
.q-item__section.column {
    width: auto !important;
}
.q-item__section.column {
    min-width: 10px !important;
}
i.q-tree__arrow {
    margin-right: 15px;
}
.q-tree__node--selected {
    background: #eeeeee !important;
    font-weight: bold;
}
.drag-container {
    position: relative;
    padding: 2px 0;
}

.cursor-grab {
    cursor: grab;
}

.cursor-grab:active {
    cursor: grabbing;
}

.node-wrapper {
    position: relative;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: grab;
    user-select: none;

    &:hover {
        background-color: rgba(0, 0, 0, 0.04);
    }

    &.is-dragging {
        opacity: 0.5;
        cursor: grabbing;
    }
}

.drop-indicator {
    position: absolute;
    left: 0;
    right: 0;
    height: 2px;
    background-color: blue;
    z-index: 10;
    pointer-events: none;

    &.drop-indicator-before {
        top: -2px;
    }

    &.drop-indicator-after {
        bottom: -2px;
    }

    &.drop-indicator-child {
        height: auto;
        top: 0;
        bottom: 0;
        background-color: rgba(blue, 0.2);
        border: 1px dashed blue;
        border-radius: 4px;
    }
}

.drag-container.drag-over {
    background-color: rgba(25, 118, 210, 0.1);
}

.highlighted-node {
    background-color: rgba(0, 150, 255, 0.2);
    box-shadow: 0 0 0 2px rgba(0, 150, 255, 0.5);
    transition: all 0.5s ease;
}
</style>
