<template>
    <div class="accordion-item">
        <div
            :id="`panelsStayOpen-collapse-${id}`"
            class="accordion-collapse collapse show"
            :aria-labelledby="`panelsStayOpen-${id}`"
        >
            <div class="accordion-body">
                <component
                    :is="currentComponent"
                    :key="componentKey"
                    :config-id="id"
                    :module="module"
                    :title="title"
                />
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch, computed } from "vue";
import Dashboard from "./Dashboard.vue";
import Expense from "./Expense.vue";
import Income from "./Income.vue";
import Balance from "./Balance.vue";

export default {
    name: "ContentConfig",
    props: {
        id: [String, Number],
        module: String,
        title: String,
    },
    components: {
        Dashboard,
        Expense,
        Income,
        Balance
    },
    setup(props, { emit }) {
        const componentKey = ref(0);

        // Mapeo de módulos a componentes
        const componentMap = {
            Dashboard: "Dashboard",
            Expense: "Expense",
            Income: "Income",
            Balance: "Balance",
        };

        const currentComponent = computed(() => {
            return componentMap[props.module] || null;
        });

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            await render();
        };

        const render = async () => {
            componentKey.value++;
        };

        // Watch para cambios en las props
        watch(() => props.module, (newModule) => {
            if (newModule) {
                render();
            }
        });

        watch(() => props.id, (newId) => {
            if (newId) {
                componentKey.value++;
            }
        });

        return {
            componentKey,
            currentComponent
        };
    },
};
</script>

<style scoped>
.accordion-item {
    margin-bottom: 1rem;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

.accordion-button {
    font-weight: 600;
    background-color: #f8f9fa;
}

.accordion-button:not(.collapsed) {
    background-color: #e9ecef;
    color: #495057;
}

.accordion-body {
    padding: 1.5rem;
    background-color: #fff;
}
</style>
