import { LocalStorage } from "../../../public/plugins/quasar/js/quasar.umd.prod";

export const setToLocalStorage = (key, value) => {
    LocalStorage.set(key, value);
};

export const getFromLocalStorage = (key) => {
    const value = LocalStorage.getItem(key);
    return value === "null" ? null : value;
};

export function useLocalStorage() {
    return {
        setToLocalStorage,
        getFromLocalStorage,
    };
}
