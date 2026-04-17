<template>
    <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
        <li class="nav-item" v-for="tab in tabs">
            <a
                :class="`nav-link ${getActiveTab.data[tab.props.tab] ? 'active' : ''}`"
                data-bs-toggle="tab"
                :href="`#${tab.props.tab}`"
                role="tab"
                @click="$emit('changeTab', { tab: tab.props.tab })"
            >
                <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                <span class="d-none d-sm-block">{{ tab.props.title }}</span>
            </a>
        </li>
    </ul>
    <div class="tab-content" id="nav-tabContent">
        <slot></slot>
    </div>
</template>

<script>
import {reactive, ref, watch} from "vue";

export default {
    name: "Tabs",
    props: {
        tabss: Object||Array
    },
    emits: ['changeTab'],
    setup(props, {slots}) {
        const tabs = ref(_.filter(slots.default(), slot => {
            return slot.props;
        }));

        const getActiveTab = reactive({data: []});

        const setGetActiveTab = (alltabs) => {
            let items = []
            _.forEach(alltabs, (v,k) => {
                items[k] = v;
            })

            getActiveTab.data = items;
        }

        setGetActiveTab(props.tabss)

        watch(
            () => props.tabss,
            (tabss, tabssBefore) => {
                setGetActiveTab(tabss)
            }
        );

        return {
            tabs,
            getActiveTab,
            setGetActiveTab
        };
    },
};
</script>

<style scoped></style>
