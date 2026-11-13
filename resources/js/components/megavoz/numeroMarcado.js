// Lo que se escribe o se pega en «Número a marcar» del teléfono web, listo para marcar.
//
// La gente copia los números como vienen en la ficha o en WhatsApp: «55 0000 1234»,
// «(55) 0000-1234», «+52 1 55 0000 1234». El teléfono los mandaba tal cual y la central
// no los reconocía: la llamada no salía. Aquí se quitan los separadores y los prefijos de
// México (+52, 52, 52 1, 044, 045, 01) se reducen a los 10 dígitos que pide la troncal.
// El plan de marcado acepta también +52 y 52 (para Zoiper y los teléfonos de escritorio).

const SEPARADORES = /[\s  -​\-‐‑–—().\/]/g;

export function normalizarNumero(texto) {
    let n = String(texto ?? '').trim().replace(SEPARADORES, '');
    if (/^web\d+$/i.test(n)) return n.toLowerCase();

    const mas = n.startsWith('+');
    if (mas) n = n.slice(1);
    if (! /^\d+$/.test(n)) return (mas ? '+' : '') + n;

    if (n.length === 13 && n.startsWith('521')) return n.slice(3);   // +52 1 + 10 (celular, formato viejo)
    if (n.length === 12 && n.startsWith('52')) return n.slice(2);    // +52 + 10
    if (n.length === 13 && /^04[45]/.test(n)) return n.slice(3);     // 044 / 045 + 10
    if (n.length === 12 && n.startsWith('01')) return n.slice(2);    // 01 + 10
    return (mas ? '+' : '') + n;
}

// { numero, tipo: 'interno' | 'nacional' | 'invalido' | null, motivo }
export function revisarNumero(texto) {
    const numero = normalizarNumero(texto);
    if (! numero) return { numero, tipo: null, motivo: '' };
    if (/^(web)?[1-9]\d{2,3}$/i.test(numero)) return { numero, tipo: 'interno', motivo: '' };
    if (/^[2-9]\d{9}$/.test(numero)) return { numero, tipo: 'nacional', motivo: '' };

    const digitos = numero.replace(/^\+/, '');
    let motivo = 'Número no válido.';
    if (! /^\d+$/.test(digitos)) {
        motivo = 'Solo números: quita letras y símbolos.';
    } else if (numero.startsWith('+') || digitos.length > 10) {
        motivo = 'Llamadas internacionales no habilitadas: marca un número de México de 10 dígitos.';
    } else if (digitos.length > 4 && digitos.length < 10) {
        motivo = `Faltan dígitos: los números de México son de 10 (llevas ${digitos.length}).`;
    }
    return { numero, tipo: 'invalido', motivo };
}
