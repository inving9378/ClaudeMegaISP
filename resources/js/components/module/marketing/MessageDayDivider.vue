<template>
  <div class="mkt-dia" role="separator" :aria-label="etiqueta">
    <span class="mkt-dia__etiqueta">{{ etiqueta }}</span>
  </div>
</template>

<script>
/** Separador de día en la conversación (misma prop `date`). */
export default {
  name: "MessageDayDivider",
  props: { date: { type: String, required: true } },
  computed: {
    etiqueta() {
      const zona = { timeZone: "America/Mexico_City" };
      const clave = (d) => new Intl.DateTimeFormat("en-CA", { ...zona, year: "numeric", month: "2-digit", day: "2-digit" }).format(d);
      const d = new Date(this.date);
      if (Number.isNaN(d.getTime())) return "";
      const hoy = new Date();
      const ayer = new Date(Date.now() - 86400000);
      if (clave(d) === clave(hoy)) return "Hoy";
      if (clave(d) === clave(ayer)) return "Ayer";
      return new Intl.DateTimeFormat("es-MX", { ...zona, weekday: "short", day: "numeric", month: "short" }).format(d).replace(/\./g, "");
    },
  },
};
</script>

<style scoped>
.mkt-dia { display: flex; justify-content: center; padding: 8px 0; }
.mkt-dia__etiqueta {
  padding: 2px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
  color: var(--mkt-texto-suave, #74788d);
  background: var(--mkt-fondo-tarjeta, #fff);
  border: 1px solid var(--mkt-borde, #e9e9ef);
}
</style>
