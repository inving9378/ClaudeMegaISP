<template>
  <!-- "Mi dinero" dentro de la ficha (2026-10-23): lo que antes estaba en el Portal — cuenta de la
       semana, desglose de lo producido, fondo y préstamos. Solo lectura, misma fuente que el Portal
       (/talento/portal/dinero/*, self-scoped por Actor); el front no recalcula ningún monto. -->
  <div class="talento-mi-dinero">
    <div v-if="semana" class="tc-muted-txt mb-2">
      Semana de pago: {{ dia(semana.period_start) }} → {{ dia(semana.period_end) }}
    </div>

    <ul class="nav nav-pills nav-sm mb-3 gap-1">
      <li v-for="t in pestanas" :key="t.key" class="nav-item">
        <a class="nav-link py-1 px-3 small" :class="{ active: tab === t.key }" href="#" @click.prevent="tab = t.key">{{ t.label }}</a>
      </li>
    </ul>

    <div v-if="cargando" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

    <template v-else>
      <!-- Cuenta: lo ya registrado en la semana, agrupado por concepto -->
      <div v-if="tab === 'cuenta'">
        <!-- UNI-T H3: etapas del dinero de la semana (solo si el backend las manda) -->
        <div v-if="cuenta && cuenta.etapas" class="row g-2 mb-3 text-center small">
          <div class="col-6 col-md-3"><div class="tc-l">Pendiente de validar</div><div>{{ cuenta.etapas.pendiente.unidades }} u. · ~{{ money(cuenta.etapas.pendiente.importe_estimado) }}</div></div>
          <div class="col-6 col-md-3"><div class="tc-l">Validado</div><div>{{ money(cuenta.etapas.validado.importe) }}</div></div>
          <div class="col-6 col-md-3"><div class="tc-l">Liquidado</div><div>{{ money(cuenta.etapas.liquidado.importe) }}</div></div>
          <div class="col-6 col-md-3"><div class="tc-l">Pagado</div><div>{{ money(cuenta.etapas.pagado.importe) }}</div></div>
        </div>
        <template v-if="cuenta && cuenta.conceptos && cuenta.conceptos.length">
          <div class="tc-kpi mb-3">
            <!-- Sin calcular la semana, aquí solo hay bonos y descuentos: el sueldo entra al calcularla. -->
            <div class="tc-l">{{ cuenta.calculada ? 'Neto de la semana' : 'Bonos y descuentos de la semana' }}</div>
            <div class="tc-n" :class="cuenta.neto < 0 ? 'text-danger' : 'text-success'">{{ money(cuenta.neto) }}</div>
            <div class="tc-l mt-1">Abonos {{ money(cuenta.total_credito) }} · Descuentos {{ money(cuenta.total_debito) }}</div>
            <div v-if="!cuenta.calculada" class="tc-l mt-1">
              Tu sueldo se suma cuando se calcula la semana. Lo que vas a cobrar está en
              <a href="#" @click.prevent="tab = 'desglose'">Desglose</a>.
            </div>
          </div>
          <div class="tc-card">
            <ul class="list-group list-group-flush">
              <li v-for="(c, i) in cuenta.conceptos" :key="i" class="list-group-item bg-transparent d-flex justify-content-between small">
                <span>{{ concepto(c.concepto) }} <span class="tc-muted-txt">· {{ c.tipo === 'credit' ? 'Abono' : 'Descuento' }}</span></span>
                <span :class="c.tipo === 'credit' ? 'text-success' : 'text-danger'">{{ c.tipo === 'credit' ? '+' : '−' }}{{ money(c.subtotal) }}</span>
              </li>
            </ul>
          </div>
        </template>
        <div v-else class="text-center text-muted small py-4">
          <i class="fa fa-receipt fa-2x d-block mb-2"></i>
          Sin movimientos todavía. Tu cuenta se llena cuando se cierra la semana de pago (el sábado).
          Mientras tanto, en <a href="#" @click.prevent="tab = 'desglose'">Desglose</a> ves lo que llevas producido.
        </div>
      </div>

      <!-- Desglose: proyección de la semana en curso (unidades contra cuota) -->
      <div v-if="tab === 'desglose'">
        <template v-if="desglose">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="tc-kpi h-100">
                <!-- Lo que pagaría «Calcular», igual que ve el Mostrador (2026-10-28). -->
                <div class="tc-l">{{ pago && pago.cerrada ? 'Pago de la semana' : 'Pago proyectado' }}</div>
                <div class="tc-n">{{ money(desglose.projected_pay) }}</div>
                <div v-if="pago" class="tc-l mt-1">
                  <span class="text-nowrap">Sueldo base {{ money(pago.sueldo_base) }}</span><span v-if="pago.sueldo_base < desglose.base_salary" class="text-nowrap"> (descuenta faltas)</span>
                  · <span class="text-nowrap">Sobreproducción {{ money(pago.sobreproduccion) }}</span>
                  <template v-if="pago.bonos > 0"> · <span class="text-nowrap">Bonos +{{ money(pago.bonos) }}</span></template>
                  <template v-if="pago.descuentos > 0"> · <span class="text-nowrap">Penalizaciones y descuentos −{{ money(pago.descuentos) }}</span></template>
                  <template v-if="pago.prestamo_y_fondo > 0"> · <span class="text-nowrap">Préstamo y fondo −{{ money(pago.prestamo_y_fondo) }}</span></template>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="tc-kpi h-100">
                <div class="tc-l">Unidades de la semana</div>
                <div class="tc-n">{{ desglose.units }} <span class="tc-l">/ cuota {{ desglose.quota }}</span></div>
                <div class="progress mt-2" style="height:8px">
                  <div class="progress-bar bg-success" :style="{ width: pctCuota + '%' }"></div>
                </div>
                <div class="tc-l mt-2">
                  Valor por unidad {{ money(desglose.value_per_unit) }}
                  <span v-if="desglose.over_units > 0"> · {{ desglose.over_units }} unidades sobre la cuota</span>
                </div>
                <div v-if="desglose.units_task || desglose.units_external" class="tc-l mt-1">
                  Incluye {{ desglose.units_wo }} de órdenes, {{ desglose.units_task }} de campo y {{ desglose.units_external }} de proyectos.
                </div>
              </div>
            </div>
          </div>
        </template>
        <div v-else class="text-center text-muted small py-4">No se pudo calcular el desglose de esta semana.</div>
      </div>

      <!-- Fondo de ahorro -->
      <div v-if="tab === 'fondo'">
        <template v-if="fondo && fondo.fondos && fondo.fondos.length">
          <div class="tc-kpi mb-3">
            <div class="tc-l">Aporte de la semana</div>
            <div class="tc-n">{{ money(fondo.aporte_periodo) }}</div>
            <div class="tc-l mt-1">Es ahorro que recuperas, no un descuento por penalización.</div>
          </div>
          <div v-for="(f, i) in fondo.fondos" :key="i" class="tc-card">
            <div class="p-3">
              <div class="d-flex justify-content-between align-items-center">
                <strong class="small">{{ proposito(f.purpose) }}</strong>
                <span class="tc-status" :class="f.status === 'spent' ? 'is-info' : 'is-ok'">{{ estado(f.status) }}</span>
              </div>
              <div class="tc-l mt-1">Acumulado {{ money(f.accumulated) }}<span v-if="f.target"> de {{ money(f.target) }}</span></div>
              <div v-if="f.target" class="progress mt-2" style="height:8px">
                <div class="progress-bar bg-success" :style="{ width: Math.min(100, f.progress_pct || 0) + '%' }"></div>
              </div>
              <div class="tc-l mt-2">
                Aporte semanal {{ money(f.weekly_deduction) }}
                <span v-if="f.weeks_remaining != null"> · faltan {{ f.weeks_remaining }} semanas</span>
              </div>
            </div>
          </div>
        </template>
        <div v-else class="text-center text-muted small py-4">
          <i class="fa fa-piggy-bank fa-2x d-block mb-2"></i>No tienes fondos de ahorro. Aquí aparecerán cuando te autoricen uno.
        </div>
      </div>

      <!-- Préstamos -->
      <div v-if="tab === 'prestamos'">
        <template v-if="prestamos && prestamos.prestamos && prestamos.prestamos.length">
          <div class="tc-kpi mb-3">
            <div class="tc-l">Abono de la semana</div>
            <div class="tc-n">{{ money(prestamos.repago_periodo) }}</div>
          </div>
          <div v-for="(l, i) in prestamos.prestamos" :key="i" class="tc-card">
            <div class="p-3">
              <div class="d-flex justify-content-between align-items-center">
                <strong class="small">{{ l.reason || 'Préstamo' }}</strong>
                <span class="tc-status" :class="l.status === 'paid' ? 'is-ok' : 'is-warn'">{{ estado(l.status) }}</span>
              </div>
              <div class="tc-l mt-1">Te falta {{ money(l.balance) }} de {{ money(l.amount) }} · llevas pagado {{ money(l.paid) }}</div>
              <div class="tc-l mt-1">
                Abono semanal {{ money(l.repayment_weekly) }}
                <span v-if="l.weeks_remaining != null"> · faltan {{ l.weeks_remaining }} semanas</span>
              </div>
            </div>
          </div>
        </template>
        <div v-else class="text-center text-muted small py-4">
          <i class="fa fa-university fa-2x d-block mb-2"></i>No tienes préstamos.
        </div>
      </div>
    </template>
  </div>
</template>

<script>
const CONCEPTOS = {
  salary_base: "Sueldo base", overproduction: "Sobreproducción", bonus: "Bonos",
  penalty: "Penalización", penalty_reversal: "Penalización revertida",
  fund_contribution: "Aporte a fondo", loan_repayment: "Abono a préstamo",
  advance: "Adelanto", adjustment: "Ajuste", embajador: "Embajadores",
};
const ESTADOS = {
  accumulating: "Ahorrando", ready: "Listo para usarse", spent: "Usado",
  active: "Activo", paid: "Pagado", cancelled: "Cancelado",
};
const PROPOSITOS = { license: "Licencia de conducir", other: "Otro" };

export default {
  name: "TalentoMiDinero",
  data() {
    return {
      tab: "cuenta",
      pestanas: [
        { key: "cuenta", label: "Cuenta" },
        { key: "desglose", label: "Desglose" },
        { key: "fondo", label: "Fondo" },
        { key: "prestamos", label: "Préstamos" },
      ],
      cargando: true,
      cuenta: null,
      desglose: null,
      fondo: null,
      prestamos: null,
    };
  },
  computed: {
    semana() { return this.cuenta || this.desglose; },
    pago() { return this.desglose && this.desglose.pago; },
    pctCuota() {
      const d = this.desglose;
      return d && d.quota ? Math.min(100, Math.round(((d.units || 0) / d.quota) * 100)) : 0;
    },
  },
  mounted() {
    this.cargar();
  },
  methods: {
    async cargar() {
      this.cargando = true;
      const pedir = (url) => axios.get(url).then((r) => r.data).catch(() => null);
      [this.cuenta, this.desglose, this.fondo, this.prestamos] = await Promise.all([
        pedir("/talento/portal/dinero/cuenta"),
        pedir("/talento/portal/dinero/desglose"),
        pedir("/talento/portal/dinero/fondo"),
        pedir("/talento/portal/dinero/prestamos"),
      ]);
      this.cargando = false;
    },
    // "-$200.00", no "$-200.00": el neto queda negativo cuando los descuentos superan lo ganado.
    money(n) {
      const v = Number(n || 0);
      return (v < 0 ? "-$" : "$") + Math.abs(v).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    concepto(c) { return CONCEPTOS[c] ?? c; },
    estado(s) { return ESTADOS[s] ?? s; },
    proposito(p) { return PROPOSITOS[p] ?? p; },
    // "2026-10-10" → "10 oct" en hora local (new Date('2026-10-10') sería el día anterior en México).
    dia(d) {
      const m = String(d || "").match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (!m) return d || "";
      return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])).toLocaleDateString("es-MX", { day: "numeric", month: "short" });
    },
  },
};
</script>
