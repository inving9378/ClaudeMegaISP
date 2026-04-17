import moment from "moment";

const newReceiptNumber = () => {
    let date = moment.now();
    return `${moment(date).format("YYYY-MM-DD")}-${date}`;
};

const removeAccents = (str) => {
    if (!str) return "";
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
};

const isEmpty = (val) => {
    return (
        val === undefined ||
        val === null ||
        (typeof val === "string" && val.trim().length === 0) ||
        (Array.isArray(val) && val.length === 0) ||
        (typeof val === "object" && Object.keys(val).length === 0)
    );
};

const defaultValue = (val, def = "No establecido") => {
    return isEmpty(val) ? def : val;
};

const translate = (val) => {
    let dic = {
        Disabled: "Deshabilitado",
        Enabled: "Habilitado",
        Inactive: "Deshabilitado",
        Inactivo: "Deshabilitado",
    };
    return dic[val] ?? val;
};

const openIp = (ip) => {
    window.open(`http://${ip}`);
};

export function useUtils() {
    return {
        newReceiptNumber,
        removeAccents,
        isEmpty,
        defaultValue,
        openIp,
        translate,
    };
}
