import { ref, computed, watch } from "vue";
import { getFromLocalStorage } from "./useLocalStorage";
import {
    dialogs,
    currentObject,
    showRoutesLayer,
    getLayerByKeyProperty,
} from "../components/module/maps/helper/mapUtils";
import { routes } from "./useMapConnections";

let firstInit = true;

export const currentNode = ref(null);
export const allNodes = ref([]);
export const tickedNodes = ref([]);
export const expandedNodes = ref(["root-node"]);
export const currentLayerNode = ref(null);

// MR-22 Fase 3a (item roadmap #9990515): id (campo `key`) del nodo seleccionado en el mapa
// (click sobre una capa), consumido por deriveThreeLevelTree() y por el panel lateral de la
// siguiente fase. null = sin selección.
export const selectedNodeId = ref(null);

// MR-22 Fase 3c (item roadmap #9990517): visibilidad de la sección "árbol derivado" (Fase 3b) en
// el panel lateral, controlada por el botón toggle de la top bar del mapa. Persistida en
// localStorage con la misma convención sin sufijo de usuario que ya usa el módulo (filter-tree,
// expanded-nodes, tickeds-nodes, map-zoom, map-center: ninguna namespacea por user_id). Cerrado
// por default.
export const arbolDerivadoVisible = ref(
    getFromLocalStorage("arbol-derivado-visible") ?? false
);

export const setNodes = (nodes) => {
    allNodes.value = nodes;
};

export const addNode = (node) => {
    allNodes.value.push(node);
};

export const deleteNode = (nodeKey) => {
    const index = allNodes.value.findIndex((n) => n.key === nodeKey);
    if (index >= 0) {
        allNodes.value.splice(index, 1);
    }
};

export const getNodeByKey = (key) => {
    return allNodes.value.find((cn) => cn.key === key) ?? null;
};

export const nodeMap = computed(() => {
    const map = {};
    allNodes.value.forEach((item) => {
        map[item.key] = { ...item, children: [] };
    });
    return map;
});

export const treeData = computed(() => {
    const map = nodeMap.value;
    const tree = [];
    allNodes.value.forEach((item) => {
        const node = map[item.key];
        if (item.parent_key === null) {
            tree.push(node);
        } else {
            const parentNode = map[item.parent_key];
            if (parentNode) {
                parentNode.children.push(node);
            }
        }
    });
    return tree;
});

// MR-22 Fase 3a (item roadmap #9990515): función PURA (sin dependencias reactivas) que deriva
// un árbol de máx. 3 niveles a partir de `rootNodeId`, usando el árbol ya cargado por
// useNodeMap() (pásale `nodeMap.value`, cuyos nodos ya traen `.children` resueltos por
// `treeData`). NO toca ni reemplaza el árbol original de 7 niveles (D22) — es una vista
// derivada aditiva para el panel lateral de la fase siguiente.
// nivel1 = hijos directos de rootNodeId · nivel2 = nietos · nivel3 = bisnietos, colapsados
// (su `.children` se vacía a propósito; `hasMoreChildren` indica si en el árbol real tienen
// más descendientes no mostrados).
export const deriveThreeLevelTree = (rootNodeId, fullTree) => {
    const rootNode = fullTree ? fullTree[rootNodeId] : null;
    if (!rootNode || !Array.isArray(rootNode.children)) {
        return [];
    }

    const cloneAtLevel = (node, level) => {
        const children = Array.isArray(node.children) ? node.children : [];
        const clone = { ...node };
        if (level >= 3) {
            clone.children = [];
            clone.hasMoreChildren = children.length > 0;
        } else {
            clone.children = children.map((child) =>
                cloneAtLevel(child, level + 1)
            );
            clone.hasMoreChildren = false;
        }
        return clone;
    };

    return rootNode.children.map((child) => cloneAtLevel(child, 1));
};

export const sincronizeRoutes = () => {
    if (currentNode) {
        let layers = currentNode.value.layers ?? [];
        routes.value.forEach((r) => {
            if (!layers.includes(r.id)) {
                layers.push(r.id);
                const node = getNodeByKey(r.key);
                if (node) {
                    node.coords = r.coords;
                    const l = getLayerByKeyProperty(r.key);
                    if (l) {
                        l.setLatLngs(r.coords);
                    }
                }
            }
        });
        currentNode.value.layers = layers;
        showRoutesLayer(currentNode.value);
    }
};

watch(treeData, () => {
    if (firstInit) {
        tickedNodes.value = getFromLocalStorage("tickeds-nodes") ?? [];
        const dialog = getFromLocalStorage("dialog-config") ?? null;
        let layer = getFromLocalStorage("layer-config");
        if (dialog && layer) {
            layer = JSON.parse(layer);
            currentObject.value = getNodeByKey(layer.key) ?? layer;
            dialogs.value[dialog] = true;
        }
        firstInit = false;
    }
});

export function useNodeMap() {
    return {
        allNodes,
        nodeMap,
        treeData,
        getNodeByKey,
        addNode,
        deleteNode,
        setNodes,
        selectedNodeId,
        deriveThreeLevelTree,
        arbolDerivadoVisible,
    };
}
