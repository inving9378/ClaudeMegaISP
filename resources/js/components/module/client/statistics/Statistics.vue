<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="p-3">
                    <q-tabs
                        v-model="currentTab"
                        no-caps
                        dense
                        active-color="indigo-6"
                        @update:model-value="onChangeTab"
                    >
                        <q-tab
                            name="internet"
                            label="Internet"
                            style="width: 25%"
                        />
                    </q-tabs>
                    <q-tab-panels
                        v-model="currentTab"
                        animated
                        :dark="darkMode"
                    >
                        <q-tab-panel name="internet">
                            <ClientStatistics :id="id" />
                        </q-tab-panel>
                    </q-tab-panels>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {onMounted, reactive, ref} from "vue";
import {darkMode} from "../../../../hook/appConfig.js";
import ClientStatistics from "./ClientStatistics.vue";

export default {
    name: "Statistics",
    props: {
        id: {
            type: String
        }
    },
    components: {
        ClientStatistics
    },
    setup(props) {
        const currentTab = ref(null);
        onMounted(() => {
            setTab();
        });

        const setTab = async () => {
            currentTab.value = "internet";
        };

        const onChangeTab = (tab) => {

        };

        return {
            currentTab,
            onChangeTab,
            darkMode,
        };
    },
};
</script>
