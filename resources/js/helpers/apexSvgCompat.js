// ApexCharts 7 (bundle) y ApexCharts 3.26 (global, tabla packages) comparten window.SVG.
// El 3.26 carga después y pisa el SVG de la v7 → "t.put is not a function".
// La v7 solo llama window.SVG() sin argumentos; el 3.26 solo lee propiedades (Doc, Filter, xlink).
const svgV7 = window.SVG;

if (typeof svgV7 === "function") {
    let actual = svgV7;
    Object.defineProperty(window, "SVG", {
        configurable: true,
        get() {
            return actual;
        },
        set(viejo) {
            if (viejo === svgV7 || typeof viejo !== "function") {
                actual = viejo;
                return;
            }
            actual = new Proxy(viejo, {
                apply(target, thisArg, args) {
                    return args.length === 0
                        ? Reflect.apply(svgV7, thisArg, args)
                        : Reflect.apply(target, thisArg, args);
                },
            });
        },
    });
}
