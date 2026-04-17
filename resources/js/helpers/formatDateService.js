import { parse, format, isValid, fromUnixTime } from 'date-fns';

export function formatDateWithTime(date) {
    const formatWithTime = "dd-MM-yyyy HH:mm:ss"; // Formato fijo

    if (!date) {
        // Si la fecha es nula o no definida, devolver una cadena vacía
        return '';
    }

    try {
        // Si date ya es un objeto Date, formatearlo directamente
        if (date instanceof Date) {
            return format(date, formatWithTime);
        }

        // Si date es un número, asumir que es un timestamp UNIX
        if (typeof date === 'number') {
            return format(fromUnixTime(date), formatWithTime);
        }

        // Si date es una cadena en formato 'YYYY-MM-DD'
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
            const parsedDate = parse(date, 'yyyy-MM-dd', new Date());
            return isValid(parsedDate) ? format(parsedDate, formatWithTime) : 'Invalid date format';
        }

        // Si date es una cadena en formato 'YYYY-MM-DD HH:mm:ss'
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(date)) {
            const parsedDate = parse(date, 'yyyy-MM-dd HH:mm:ss', new Date());
            return isValid(parsedDate) ? format(parsedDate, formatWithTime) : 'Invalid date format';
        }

        // Si date es una cadena en formato 'YYYY-MM-DD HH:mm'
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(date)) {
            const parsedDate = parse(date, 'yyyy-MM-dd HH:mm', new Date());
            return isValid(parsedDate) ? format(parsedDate, formatWithTime) : 'Invalid date format';
        }

        // Si date es una cadena en formato ISO 8601
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/.test(date)) {
            const parsedDate = new Date(date);
            return isValid(parsedDate) ? format(parsedDate, formatWithTime) : 'Invalid date format';
        }

        // Intentar parsear cualquier otro formato
        const parsedDate = new Date(date);
        return isValid(parsedDate) ? format(parsedDate, formatWithTime) : 'Invalid date format';
    } catch (error) {
        // Manejar cualquier error en el formato de la fecha
        return 'Invalid date format';
    }
}
