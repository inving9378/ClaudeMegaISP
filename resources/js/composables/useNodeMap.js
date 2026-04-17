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
    };
}
