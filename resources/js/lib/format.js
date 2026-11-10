export function rupiah(value) {
    const formatted = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0);
    return `Rp${formatted}`;
}

export function formatDate(date) {
    if (!date) return '';
    const [year, month, day] = date.split('-');
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Add days to a Y-m-d string without leaving the local calendar day.
 */
export function addDays(date, days) {
    if (!date) return '';
    const [year, month, day] = date.split('-').map(Number);
    const result = new Date(year, month - 1, day + days);
    const pad = (value) => String(value).padStart(2, '0');
    return `${result.getFullYear()}-${pad(result.getMonth() + 1)}-${pad(result.getDate())}`;
}