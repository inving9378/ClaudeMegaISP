import { testPattern } from "./patterns";

export const rules = {
    required: (val) => !!val || "Requerido",
    ipv4: (val) => (val && testPattern.ipv4(val)) || "Formato no válido",
    ipv6: (val) => testPattern.ipv6(val) || "Formato no válido",
    mac: (val) => testPattern.mac(val) || "Formato no válido",
};
