import { computed } from 'vue';

export function useFleetFormatters() {
    const moneyFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const fmtMoney = (v) => moneyFmt.format(Number(v || 0));
    const fmtKm    = (v) => new Intl.NumberFormat('es-MX').format(Number(v || 0));
    const fmtDate  = (v) => {
        if (!v) return '—';
        const d = new Date(v);
        return isNaN(d) ? v : d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    const statusLabels   = { active: 'Activo', in_workshop: 'En taller', inactive: 'Inactivo' };
    const fuelLabels     = { gasoline: 'Gasolina', diesel: 'Diésel', electric: 'Eléctrico', hybrid: 'Híbrido' };
    const typeLabels     = { car: 'Automóvil', pickup: 'Pickup / Camioneta', truck: 'Camión', motorcycle: 'Motocicleta', other: 'Otro' };
    const maintLabels    = { preventive: 'Preventivo', corrective: 'Correctivo', emergency: 'Emergencia', verification: 'Verificación' };
    const docTypeLabels  = {
        circulation_card: 'Tarjeta de circulación', insurance_policy: 'Póliza de seguro',
        tenencia: 'Tenencia', verification: 'Verificación', operator_license: 'Licencia del operador',
        special_permit: 'Permiso especial', other: 'Otro',
    };

    const statusLabel    = (s) => statusLabels[s] || s;
    const maintTypeLabel = (t) => maintLabels[t] || t;
    const maintIcon      = (t) => ({
        preventive:   { icon: 'bi-droplet-fill',              color: 'text-success' },
        corrective:   { icon: 'bi-exclamation-triangle-fill', color: 'text-danger' },
        emergency:    { icon: 'bi-exclamation-octagon-fill',  color: 'text-danger' },
        verification: { icon: 'bi-patch-check-fill',          color: 'text-info' },
    }[t] || { icon: 'bi-tools', color: 'text-secondary' });

    const docIcon = (t) => ({
        circulation_card: 'bi-card-heading', insurance_policy: 'bi-shield-check',
        tenencia: 'bi-receipt', verification: 'bi-clipboard-check',
        operator_license: 'bi-person-badge', special_permit: 'bi-file-earmark-ruled',
        other: 'bi-file-earmark',
    }[t] || 'bi-file-earmark');

    const worksCatalog = [
        'Cambio de aceite', 'Filtro de aceite', 'Filtro de aire', 'Filtro de gasolina',
        'Frenos', 'Llantas', 'Suspensión', 'Afinación', 'Batería', 'Sistema eléctrico',
        'Transmisión', 'Clutch', 'Alineación y balanceo', 'Anticongelante', 'Bandas', 'Otro',
    ];

    function docStatus(d) {
        if (!d?.expiration_date) return 'vigente';
        const exp = new Date(d.expiration_date);
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const in30 = new Date(today); in30.setDate(in30.getDate() + 30);
        if (exp < today) return 'vencido';
        if (exp <= in30) return 'por_vencer';
        return 'vigente';
    }
    function docDays(d) {
        if (!d?.expiration_date) return null;
        const exp = new Date(d.expiration_date);
        const today = new Date(); today.setHours(0, 0, 0, 0);
        return Math.round((exp - today) / 86400000);
    }

    return {
        fmtMoney, fmtKm, fmtDate,
        statusLabels, fuelLabels, typeLabels, maintLabels, docTypeLabels,
        statusLabel, maintTypeLabel, maintIcon, docIcon, worksCatalog,
        docStatus, docDays,
    };
}
