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