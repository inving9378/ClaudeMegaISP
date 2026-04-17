<template>
    <div
        :key="activeTab"
        :class="`tab-pane fade ${activeTab ? 'show active' : ''}`"
        :id="tab"
        role="tabpanel"
        :aria-labelledby="`nav-${title}`"
    >
        <slot></slot>
    </div>
</template>

<script>
import {ref, watch} from "vue";

export default {
    name: "Tab",
    props: {
        tab: String,
        title: String,
        active: {
            type: Boolean,
            default: false
        }
    },
    setup(props) {
        // 3. Inicializar con el valor de la prop 'active'
        const activeTab = ref(props.active);

        watch(
            () => props.active,
            (active) => {
                activeTab.value = active;
            }
        );

        return {
            activeTab
        }
    }
};
</script>

<style scoped></style>
