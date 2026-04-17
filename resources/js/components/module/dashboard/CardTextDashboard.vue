<template>
  <div class="card-text-body">
    <p class="card-text">
      Hoy se emitirá:
      <br />- Facturas: {{ data.invoises }}
      <br />- Transacciones: {{ data.transactions }}
      <br /> Verificar la Prevista de movimiento Bancarios
    </p>
  </div>
</template>

<script>
import { onMounted, ref } from "vue";
import { requestStatisticsForTextCardInDashBoard } from "./helper/request";
export default {
  name: "CardTextDashboard",
   props: {},
   setup() {
    const data = ref({});

    onMounted(() => {
      getStatisticsForTextCardInDashBoard();
    });

    const getStatisticsForTextCardInDashBoard = async () => {
      data.value = await requestStatisticsForTextCardInDashBoard();
    };

    return {
      data,
    };
  },
};
</script>

<style scoped>
</style>
