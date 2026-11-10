// Campos de las plantillas de orden de Talento (item #1200). Lógica pura, sin Vue, para que la
// compartan el modal "Nueva orden" (Fase B), el editor de plantillas (Fase C) y la captura de
// cierre en el portal (Fase D). Es el espejo de app/Modules/Addons/Talento/Support/
// EsquemaCamposOrden.php: el servidor manda; esto solo adelanta los errores al usuario.

export const TIPOS = [
  'texto', 'texto_largo', 'numero', 'seleccion', 'multi_seleccion',
  'si_no', 'fecha', 'coordenadas', 'tabla',
];

export const MOMENTO_CREACION = 'creacion';
export const MOMENTO_CIERRE = 'cierre';

const RE_COORDENADAS = /^\s*(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/;
const RE_FECHA = /^(\d{4})-(\d{2})-(\d{2})$/;

export function esVacio(v) {
  return v === null || v === undefined
    || (typeof v === 'string' && v.trim() === '')
    || (Array.isArray(v) && v.length === 0)
    || (typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 0);
}

/** Campos de un momento (creación o cierre), en su orden. */
export function camposDe(campos, momento) {
  return (campos || []).filter(c => (c.when || MOMENTO_CREACION) === momento);
}

function errorDeValor(tipo, v, opciones) {
  switch (tipo) {
    case 'numero': {
      const s = String(v).trim().replace(',', '.');
      return s === '' || isNaN(Number(s)) || !isFinite(Number(s)) ? 'Debe ser un número.' : null;
    }
    case 'coordenadas': {
      const m = RE_COORDENADAS.exec(String(v));
      if (!m) return 'Usa el formato «latitud,longitud», por ejemplo 19.432608,-99.133209.';
      const lat = Number(m[1]); const lng = Number(m[2]);
      return lat < -90 || lat > 90 || lng < -180 || lng > 180
        ? 'Usa el formato «latitud,longitud», por ejemplo 19.432608,-99.133209.' : null;
    }
    case 'fecha': {
      const m = RE_FECHA.exec(String(v));
      if (!m) return 'Fecha no válida.';
      const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
      return d.getMonth() === Number(m[2]) - 1 && d.getDate() === Number(m[3]) ? null : 'Fecha no válida.';
    }
    case 'seleccion':
      return (opciones || []).includes(v) ? null : 'Elige una de las opciones.';
    case 'multi_seleccion':
      return Array.isArray(v) && v.every(o => (opciones || []).includes(o)) ? null : 'Una de las opciones elegidas no es válida.';
    default:
      return null;
  }
}

/**
 * Errores de los valores capturados → { key: mensaje, 'tabla.fila.columna': mensaje }.
 * Igual que el servidor: `required` no se exige en tablas, solo el tipo de cada celda.
 */
export function validarCampos(campos, valores) {
  const errores = {};
  for (const c of campos || []) {
    const v = (valores || {})[c.key];
    if (c.type === 'tabla') {
      (Array.isArray(v) ? v : []).forEach((fila, i) => {
        for (const col of c.columns || []) {
          const cv = fila ? fila[col.key] : undefined;
          if (esVacio(cv) || col.type === 'si_no') continue;
          const e = errorDeValor(col.type, cv, col.options);
          if (e) errores[`${c.key}.${i}.${col.key}`] = e;
        }
      });
      continue;
    }
    // Un sí/no sin tocar (null) cuenta como vacío; false ("No") es una respuesta.
    if (esVacio(v)) {
      if (c.required) errores[c.key] = 'Este campo es obligatorio.';
      continue;
    }
    const e = errorDeValor(c.type, v, c.options);
    if (e) errores[c.key] = e;
  }
  return errores;
}

/**
 * Valores listos para enviar: sin vacíos; tablas fijas conservan la posición de cada fila
 * (Conector 1, 2…) y recortan las vacías del final; las dinámicas descartan las vacías.
 */
export function limpiarValores(campos, valores) {
  const out = {};
  for (const c of campos || []) {
    const v = (valores || {})[c.key];
    if (c.type === 'tabla') {
      let filas = (Array.isArray(v) ? v : []).map(f => {
        const fila = {};
        for (const col of c.columns || []) {
          if (f && !esVacio(f[col.key])) fila[col.key] = f[col.key];
        }
        return fila;
      });
      if (c.dynamic) filas = filas.filter(f => Object.keys(f).length);
      else while (filas.length && !Object.keys(filas[filas.length - 1]).length) filas.pop();
      if (filas.length) out[c.key] = filas;
      continue;
    }
    if (c.type === 'si_no') {
      if (v === true || v === false) out[c.key] = v;
      continue;
    }
    if (!esVacio(v)) out[c.key] = typeof v === 'string' ? v.trim() : v;
  }
  return out;
}

/** Errores del servidor ({'datos.zona': …, 'datos.conectores.0.id': …}) → claves del componente. */
export function erroresDeServidor(errors) {
  const out = {};
  for (const [k, msg] of Object.entries(errors || {})) {
    if (k.startsWith('datos.')) out[k.slice(6)] = Array.isArray(msg) ? msg[0] : msg;
  }
  return out;
}

function numero(n) {
  const x = Number(String(n).replace(',', '.'));
  if (!isFinite(x)) return String(n);
  return Number.isInteger(x) ? String(x) : String(Number(x.toFixed(6)));
}

/** Valor como texto ("Sí", "-20.5 dBm", "09/11/2026"…). null si no hay valor. */
export function formatearValor(c, v) {
  if (esVacio(v) && v !== false) return null;
  switch (c.type) {
    case 'si_no': return v ? 'Sí' : 'No';
    case 'numero': return numero(v) + (c.unit ? ' ' + c.unit : '');
    case 'multi_seleccion': return Array.isArray(v) ? v.join(', ') : String(v);
    case 'fecha': {
      const m = RE_FECHA.exec(String(v));
      return m ? `${m[3]}/${m[2]}/${m[1]}` : String(v);
    }
    case 'tabla': return null;
    default: return String(v);
  }
}

/** Texto de un material del checklist: "Fleje: 20 m". */
export function textoMaterial(m) {
  if (m.cantidad === null || m.cantidad === undefined || m.cantidad === '') return m.label;
  return `${m.label}: ${numero(m.cantidad)}${m.unidad ? ' ' + m.unidad : ''}`;
}
