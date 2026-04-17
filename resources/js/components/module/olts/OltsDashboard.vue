<template>
    <olt-card />

    <olt-interruptions-pon />

    <olt-onus-status />
</template>

<script setup>
import OltCard from "./components/dashboard/OltCard.vue";
import OltInterruptionsPon from "./components/dashboard/OltInterruptionsPon.vue";
import OltOnusStatus from "./components/dashboard/OltOnusStatus.vue";
</script>
