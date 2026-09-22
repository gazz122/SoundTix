const map = {
    draft: { label: 'Draft', classes: 'bg-slate-100 text-slate-600 border-slate-200' },
    pending: { label: 'Menunggu Persetujuan', classes: 'bg-amber-50 text-amber-700 border-amber-200' },
    published: { label: 'Published', classes: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    rejected: { label: 'Ditolak', classes: 'bg-rose-50 text-rose-700 border-rose-200' },
    pending_cancellation: { label: 'Menunggu Persetujuan Pembatalan', classes: 'bg-orange-50 text-orange-700 border-orange-200' },
    cancelled: { label: 'Dibatalkan', classes: 'bg-slate-100 text-slate-600 border-slate-200' },
    completed: { label: 'Selesai', classes: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
};

export default function StatusBadge({ status }) {
    const badge = map[status] ?? map.draft;
    return (
        <span className={`inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium border ${badge.classes}`}>
            {badge.label}
        </span>
    );
}