<template>
  <div class="firma-canvas-wrap">
    <canvas ref="canvasEl" class="firma-canvas" :class="{ 'firma-canvas--disabled': disabled }"></canvas>
  </div>
</template>

<script>
/**
 * Componente aislado de captura de firma en <canvas>.
 *
 * Unifica mouse, dedo (touch) y lápiz/stylus vía signature_pad (ya es dependencia
 * del proyecto — se usa igual en TalentoExpedienteDocumentos.vue). No hace llamadas
 * al backend ni se acopla a ninguna pantalla: expone métodos (ref) para que quien lo
 * integre (ej. expediente digital 9A, #9990792) obtenga el PNG y los trazos cuando
 * lo necesite.
 *
 * Uso típico del padre:
 *   <FirmaCanvas ref="firma" />
 *   ...
 *   if (!this.$refs.firma.isEmpty()) {
 *     const png = this.$refs.firma.toPNG();
 *     const trazos = this.$refs.firma.getTrazos(); // evidencia: puntos + tiempos
 *   }
 */
import SignaturePad from "signature_pad";

export default {
  name: "FirmaCanvas",
  props: {
    penColor: { type: String, default: "black" },
    backgroundColor: { type: String, default: "rgba(0,0,0,0)" },
    minWidth: { type: Number, default: 0.5 },
    maxWidth: { type: Number, default: 2.5 },
    disabled: { type: Boolean, default: false },
  },
  emits: ["begin", "end", "change"],
  data() {
    return {
      pad: null,
    };
  },
  watch: {
    disabled(val) {
      if (!this.pad) return;
      if (val) this.pad.off();
      else this.pad.on();
    },
  },
  mounted() {
    this.pad = new SignaturePad(this.$refs.canvasEl, {
      backgroundColor: this.backgroundColor,
      penColor: this.penColor,
      minWidth: this.minWidth,
      maxWidth: this.maxWidth,
    });
    this.pad.addEventListener("beginStroke", () => this.$emit("begin"));
    this.pad.addEventListener("endStroke", () => {
      this.$emit("end");
      this.$emit("change");
    });
    if (this.disabled) this.pad.off();

    this._onResize = () => this.resize();
    window.addEventListener("resize", this._onResize);
    this.resize();
  },
  beforeUnmount() {
    window.removeEventListener("resize", this._onResize);
    if (this.pad) this.pad.off();
  },
  methods: {
    // Redimensiona el canvas a su tamaño real en pantalla (devicePixelRatio) sin
    // perder el trazo ya dibujado — necesario porque un <canvas> pierde su bitmap
    // al cambiar width/height.
    resize() {
      const canvas = this.$refs.canvasEl;
      if (!canvas || !this.pad) return;
      const data = this.pad.isEmpty() ? null : this.pad.toData();
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      canvas.width = canvas.offsetWidth * ratio;
      canvas.height = canvas.offsetHeight * ratio;
      canvas.getContext("2d").scale(ratio, ratio);
      this.pad.clear();
      if (data) this.pad.fromData(data);
    },
    clear() {
      if (!this.pad) return;
      this.pad.clear();
      this.$emit("change");
    },
    isEmpty() {
      return this.pad ? this.pad.isEmpty() : true;
    },
    // PNG en base64 (data URL) de la firma, o null si el canvas está vacío.
    toPNG() {
      return this.pad && !this.pad.isEmpty() ? this.pad.toDataURL("image/png") : null;
    },
    // Secuencia de trazos con sus tiempos: PointGroup[] de signature_pad, cada
    // grupo con points:[{x,y,pressure,time}] — evidencia de CÓMO se firmó, no
    // solo el dibujo final.
    getTrazos() {
      return this.pad ? this.pad.toData() : [];
    },
  },
};
</script>

<style scoped>
.firma-canvas-wrap {
  width: 100%;
}
.firma-canvas {
  width: 100%;
  height: 200px;
  display: block;
  border: 1px dashed #adb5bd;
  border-radius: 4px;
  background: #fff;
  touch-action: none;
  cursor: crosshair;
}
.firma-canvas--disabled {
  cursor: not-allowed;
  opacity: 0.6;
}
</style>
