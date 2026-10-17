// FR-03 (#537): campo condicional de los formularios dinámicos. Un campo cuyo `depend` tiene la
// forma «campo=valor» solo se muestra cuando ese campo del mismo formulario vale eso (p. ej. los
// meses del plazo «Especial»: depend = «duration_contract_id=<id de Especial>», que la migración
// resuelve por nombre en cada entorno). Los demás valores de `depend` («1», «option»…) no cambian.
export const campoVisible = (campo, datos) => {
    const regla = campo?.depend;
    if (typeof regla !== "string" || !/^[a-z_]+=.+$/.test(regla)) {
        return true;
    }
    const [nombre, valor] = regla.split("=");
    const actual = datos ? datos[nombre] : undefined;
    return actual !== undefined && actual !== null && String(actual) === String(valor);
};
