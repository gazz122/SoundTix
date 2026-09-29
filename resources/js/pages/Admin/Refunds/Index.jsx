import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import { rupiah } from '@/lib/format';

const statusStyles = {
    pending: 'border-amber-200 bg-amber-50/60 text-amber-700',
    completed: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
    failed: 'border-rose-200 bg-rose-50/60 text-rose-700',
    refunded: 'border-blue-200 bg-blue-50/60 text-blue-700',
};

export default function Index({ refunds, cancellations }) {
    const { flash } = usePage().props;

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Pengajuan Refund</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Kelola pengajuan refund customer dan pengajuan pembatalan event dari organizer.
                </p>
            </div>

            {flash.success && <Flash type="success">{flash.success}</Flash>}
            {flash.error && <Flash type="error">{flash.error}</Flash>}

            {cancellations.length > 0 && (
                <div className="mb-10">
                    <div className="flex items-center gap-2 mb-3">
                        <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wider">
                            Pengajuan Pembatalan Event
                        </h2>
                        <span className="rounded-full bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-0.5">
                            {cancellations.length}
                        </span>
                    </div>
                    <div className="space-y-3">
                        {cancellations.map((cancellation) => (
                            <div
                                key={cancellation.id}
                                className="bg-white border border-orange-200/80 rounded-xl overflow-hidden shadow-sm p-5"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="font-semibold text-slate-900 truncate">{cancellation.title}</h3>
                                        <p className="mt-0.5 text-sm text-slate-500">
                                            {cancellation.event_date} · Organizer: {cancellation.organizer} · Diajukan {cancellation.submitted_at}
                                        </p>
                                        <p className="mt-2 text-sm text-slate-700">
                                            Alasan: <span className="font-medium text-slate-800">{cancellation.cancellation_reason}</span>
                                        </p>
                                        <p className="mt-1 text-sm text-amber-700">
                                            {cancellation.paid_orders_count} pesanan lunas akan di-refund otomatis jika disetujui.
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(
                                                    `/admin/refunds/events/${cancellation.id}/reject-cancellation`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                                        >
                                            Tolak
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(
                                                    `/admin/refunds/events/${cancellation.id}/approve-cancellation`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                                        >
                                            Setujui & Refund Semua
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th className="px-4 py-3 text-left">Order</th>
                                <th className="px-4 py-3 text-left">Customer</th>
                                <th className="px-4 py-3 text-left">Event</th>
                                <th className="px-4 py-3 text-right">Nominal</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Alasan</th>
                                <th className="px-4 py-3 text-left">Diajukan</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {refunds.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-slate-400">
                                        Belum ada pengajuan refund.
                                    </td>
                                </tr>
                            ) : (
                                refunds.map((refund) => (
                                    <tr key={refund.id} className="hover:bg-slate-50/50">
                                        <td className="px-4 py-2.5 font-mono text-slate-800">{refund.order_code}</td>
                                        <td className="px-4 py-2.5">
                                            {refund.customer_name}
                                            <span className="block text-xs text-slate-400">{refund.customer_email}</span>
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-700">{refund.event_title ?? '-'}</td>
                                        <td className="px-4 py-2.5 text-right font-medium tabular-nums">{rupiah(refund.amount)}</td>
                                        <td className="px-4 py-2.5">
                                            <span className={`inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium border ${statusStyles[refund.status] ?? statusStyles.pending}`}>
                                                {refund.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">{refund.reason}</td>
                                        <td className="px-4 py-2.5 text-slate-500">{refund.created_at}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {refund.status === 'pending' && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(`/admin/refunds/${refund.id}/approve`, {}, { preserveScroll: true })
                                                    }
                                                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                                                >
                                                    Setujui
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function Flash({ type, children }) {
    const styles = {
        success: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
        error: 'border-rose-200 bg-rose-50/60 text-rose-700',
    };
    return <div className={`mb-6 rounded-lg border px-4 py-3 text-sm ${styles[type]}`}>{children}</div>;
}