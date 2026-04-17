<template>
    <ul class="nav nav-tabs">
        <li v-for="tab in tabs" :key="tab.id" class="nav-item">
            <a
                class="nav-link"
                :class="{ active: activeTab === tab.id }"
                href="#"
                @click.prevent="$emit('tab-change', tab.id)"
            >
                {{ tab.label }}
            </a>
        </li>
    </ul>
</template>

<script>
export default {
    name: "TabNavigation",
    props: {
        tabs: {
            type: Array,
            required: true,
        },
        activeTab: {
            type: String,
            required: true,
        },
    },
    emits: ["tab-change"],
};
</script>
