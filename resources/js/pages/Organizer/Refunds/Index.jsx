import { usePage } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import { rupiah } from '@/lib/format';

const statusStyles = {
    pending: 'border-amber-200 bg-amber-50/60 text-amber-700',
    completed: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
    failed: 'border-rose-200 bg-rose-50/60 text-rose-700',
    refunded: 'border-blue-200 bg-blue-50/60 text-blue-700',
};

export default function Index({ refunds }) {
    const { flash } = usePage().props;

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Daftar Refund</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Refund customer dari event yang Anda selenggarakan. Refund disetujui mengurangi dana penarikan event.
                </p>
            </div>

            {flash.success && <Flash type="success">{flash.success}</Flash>}
            {flash.error && <Flash type="error">{flash.error}</Flash>}

            <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th className="px-4 py-3 text-left">Event</th>
                                <th className="px-4 py-3 text-left">Customer</th>
                                <th className="px-4 py-3 text-left">Order</th>
                                <th className="px-4 py-3 text-right">Nominal</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Alasan</th>
                                <th className="px-4 py-3 text-left">Diajukan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {refunds.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-slate-400">
                                        Belum ada refund untuk event Anda.
                                    </td>
                                </tr>
                            ) : (
                                refunds.map((refund) => (
                                    <tr key={refund.id} className="hover:bg-slate-50/50">
                                        <td className="px-4 py-2.5 text-slate-800">
                                            {refund.event_title ?? '-'}
                                            {refund.event_date && (
                                                <span className="block text-xs text-slate-400">{refund.event_date}</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {refund.customer_name}
                                            <span className="block text-xs text-slate-400">{refund.customer_email}</span>
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-slate-600">{refund.order_code}</td>
                                        <td className="px-4 py-2.5 text-right font-medium tabular-nums">{rupiah(refund.amount)}</td>
                                        <td className="px-4 py-2.5">
                                            <span className={`inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium border ${statusStyles[refund.status] ?? statusStyles.pending}`}>
                                                {refund.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">{refund.reason}</td>
                                        <td className="px-4 py-2.5 text-slate-500">{refund.created_at}</td>
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