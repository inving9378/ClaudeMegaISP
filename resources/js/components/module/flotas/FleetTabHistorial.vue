<template>
    <section>
        <h6 class="flt-section-title">Historial completo</h6>
        <div v-if="!events.length" class="text-muted small">Sin eventos.</div>
        <div class="flt-timeline">
            <div class="flt-tl-item" v-for="(e, i) in events" :key="i">
                <div class="flt-tl-dot" :class="e.dot"></div>
                <div class="flt-tl-content">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold"><i :class="['bi', e.icon, 'me-1']"></i>{{ e.title }}</span>
                        <a href="#" class="small" @click.prevent="$emit('go-tab', e.tab)">Ver →</a>
                    </div>
                    <div class="text-muted small">{{ fmtDate(e.date) }} · {{ e.desc }}</div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import { computed } from 'vue';
import { useFleetFormatters } from './useFleetFormatters.js';

export default {
    name: 'FleetTabHistorial',
    props: {
        vehicle:     { type: Object, required: true },
        assignments: { type: Array, default: () => [] },
    },
    emits: ['go-tab'],
    setup(props) {
        const { fmtMoney, fmtDate, maintTypeLabel, maintIcon, docTypeLabels, docIcon } = useFleetFormatters();

        const events = computed(() => {
            const ev = [];
            (props.vehicle?.maintenances ?? []).forEach((m) => ev.push({ date: m.service_date, icon: maintIcon(m.type).icon, dot: 'bg-primary', title: `Mantenimiento ${maintTypeLabel(m.type).toLowerCase()}`, desc: fmtMoney(m.total_cost), tab: 'mantenimientos' }));
            (props.vehicle?.documents ?? []).forEach((d) => ev.push({ date: d.issue_date || d.expiration_date, icon: docIcon(d.document_type), dot: 'bg-info', title: docTypeLabels[d.document_type] || 'Documento', desc: d.expiration_date ? `Vence ${fmtDate(d.expiration_date)}` : 'Registrado', tab: 'documentos' }));
            props.assignments.forEach((a) => ev.push({ date: a.since, icon: 'bi-person-badge', dot: 'bg-success', title: 'Asignación', desc: `${a.operator_name || 'Operador'} · ${a.department || 's/depto'}`, tab: 'asignacion' }));
            (props.vehicle?.fuelLog ?? []).forEach((f) => ev.push({ date: f.refuel_date, icon: 'bi-fuel-pump-fill', dot: 'bg-warning', title: 'Carga de combustible', desc: `${Number(f.liters).toFixed(1)} L · ${fmtMoney(f.cost)}`, tab: 'combustible' }));
            (props.vehicle?.photos ?? []).forEach((p) => ev.push({ date: p.taken_at || p.created_at, icon: 'bi-image', dot: 'bg-secondary', title: 'Foto agregada', desc: p.photo_type, tab: 'fotos' }));
            return ev.filter((e) => e.date).sort((a, b) => new Date(b.date) - new Date(a.date));
        });

        return { events, fmtDate };
    },
};
</script>
