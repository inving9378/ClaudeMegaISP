const customFormat = (date) => {
    if (Array.isArray(date)) {
        return date.map((d) => formatDate(d)).join(" - ");
    }
    return formatDate(date);
};

const formatDate = (date) => {
    if (!(date instanceof Date)) {
        return "";
    }
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

const diableDatesFromWeek = (date) => {
    const dayOfWeek = date.getDay();
    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const isFutureDate = date > today;
    return !isWeekend || isFutureDate;
};

export function useDatePicker() {
    return {
        customFormat,
        formatDate,
        diableDatesFromWeek,
    };
}
