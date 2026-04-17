<template>
    <div class="row">
        <div class="col">
            <cards-info
                :olt-id="currentOlt"
                @change-tab="(t, f) => emits('change-tab', t, f)"
            />
        </div>
    </div>
    <div class="row q-col-gutter-md">
        <div class="col-xs-12 col-sm-12 col-md-8 col-lg-8 col-xl-8">
            <pon-outage
                :olt-id="currentOlt"
                @change-tab="(t, f) => emits('change-tab', t, f)"
            />
        </div>
        <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-xl-4">
            <olt-list
                @change="
                    (o) => {
                        currentOlt = o;
                    }
                "
            />
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";
import CardsInfo from "./CardsInfo.vue";
import OltList from "./OltList.vue";
import PonOutage from "./PonOutage.vue";
defineOptions({
    name: "DashboardPanel",
});

const emits = defineEmits(["change-tab", "change-olt"]);

const currentOlt = ref(null);

watch(currentOlt, (n) => {
    emits("change-olt", n);
});
</script>
