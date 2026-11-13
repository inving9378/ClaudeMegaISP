// node --test tests/js/numeroMarcado.test.mjs
// Lo que se escribe en «Número a marcar» del teléfono web (resources/js/components/megavoz/numeroMarcado.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizarNumero, revisarNumero } from '../../resources/js/components/megavoz/numeroMarcado.js';

test('quita espacios, guiones, puntos y paréntesis', () => {
    for (const escrito of ['55 0000 1234', '55-0000-1234', '(55) 0000-1234', '55.0000.1234', ' 55 0000 1234 ', '55 0000 1234']) {
        assert.equal(normalizarNumero(escrito), '5500001234', escrito);
    }
});

test('lada de país y prefijos viejos de México acaban en 10 dígitos', () => {
    for (const escrito of ['+52 55 0000 1234', '52 55 0000 1234', '+52 1 55 0000 1234', '521 55 0000 1234',
                           '044 55 0000 1234', '045 55 0000 1234', '01 55 0000 1234']) {
        const r = revisarNumero(escrito);
        assert.equal(r.numero, '5500001234', escrito);
        assert.equal(r.tipo, 'nacional', escrito);
    }
});

test('extensiones de la central', () => {
    assert.deepEqual(revisarNumero('1003'), { numero: '1003', tipo: 'interno', motivo: '' });
    assert.equal(revisarNumero(' 10 03 ').numero, '1003');
    assert.equal(revisarNumero('WEB1003').numero, 'web1003');
    assert.equal(revisarNumero('web1003').tipo, 'interno');
    assert.equal(revisarNumero('1999').tipo, 'interno');
});

test('lo que no se puede marcar dice por qué', () => {
    assert.match(revisarNumero('+1 305 555 1234').motivo, /internacionales/);
    assert.match(revisarNumero('001 305 555 1234').motivo, /internacionales/);
    assert.match(revisarNumero('55 0000 123').motivo, /Faltan dígitos.*llevas 9/);
    assert.match(revisarNumero('55 34ab 1234').motivo, /Solo números/);
    for (const escrito of ['+1 305 555 1234', '55 0000 123', '55 34ab 1234', '0550000123']) {
        assert.equal(revisarNumero(escrito).tipo, 'invalido', escrito);
    }
});

test('vacío no es error', () => {
    assert.deepEqual(revisarNumero('   '), { numero: '', tipo: null, motivo: '' });
});
