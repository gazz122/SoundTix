const map = {
    pending: { label: 'Menunggu Pembayaran', classes: 'bg-amber-50 text-amber-700 border-amber-200' },
    paid: { label: 'Lunas', classes: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    expired: { label: 'Kadaluarsa', classes: 'bg-rose-50 text-rose-700 border-rose-200' },
    cancelled: { label: 'Dibatalkan', classes: 'bg-slate-100 text-slate-600 border-slate-200' },
    failed: { label: 'Gagal', classes: 'bg-rose-50 text-rose-700 border-rose-200' },
};

export default function OrderStatusBadge({ status }) {
    const badge = map[status] ?? map.pending;
    return (
        <span className={`inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium border ${badge.classes}`}>
            {badge.label}
        </span>
    );
}