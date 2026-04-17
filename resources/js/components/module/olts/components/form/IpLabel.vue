<template>
    <div class="row">
        <div class="col col-auto">
            {{ ip }}
        </div>
        <div class="col col-auto" style="padding: 0">
            <i
                class="fas fa-share-square text-primary cursor-pointer"
                @click="openIp(ip)"
            ></i>
        </div>
    </div>
</template>

<script setup>
import { useUtils } from "../../../../../composables/useUtils";

defineOptions({
    name: "IpLabel",
});

const props = defineProps({
    ip: String,
});

const { openIp } = useUtils();
</script>
