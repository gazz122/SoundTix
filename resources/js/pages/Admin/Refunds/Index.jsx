import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
    const [completing, setCompleting] = useState(null);

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Pengajuan Refund</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Kelola pengajuan pembatalan event dari organizer dan pengembalian dana ke customer.
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
                                            onClick={() => {
                                                const count = Number(cancellation.paid_orders_count) || 0;
                                                const confirmed = window.confirm(
                                                    [
                                                        `Setujui pembatalan "${cancellation.title}"?`,
                                                        '',
                                                        count > 0
                                                            ? `${count} pesanan lunas akan di-refund ke customer:` +
                                                              `\n${rupiah(cancellation.refund_total)} akan dikembalikan.`
                                                            : 'Tidak ada pesanan lunas yang perlu di-refund.',
                                                        '',
                                                        'Tindakan ini tidak dapat dibatalkan, dana langsung diproses ke Midtrans, dan event akan dibatalkan.',
                                                    ].join('\n'),
                                                );

                                                if (confirmed) {
                                                    router.post(
                                                        `/admin/refunds/events/${cancellation.id}/approve-cancellation`,
                                                        {},
                                                        { preserveScroll: true },
                                                    );
                                                }
                                            }}
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
                                <th className="px-4 py-3 text-left">Nominal</th>
                                <th className="px-4 py-3 text-left">Rekening Tujuan</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Alasan</th>
                                <th className="px-4 py-3 text-left">Diajukan</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {refunds.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-8 text-center text-slate-400">
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
                                        <td className="px-4 py-2.5 text-slate-700">
                                            {refund.bank_name ? (
                                                <>
                                                    <span className="block">{refund.bank_name}</span>
                                                    <span className="block font-mono text-xs text-slate-500">{refund.account_number}</span>
                                                    <span className="block text-xs text-slate-500">{refund.account_holder_name}</span>
                                                </>
                                            ) : (
                                                <span className="text-slate-400">-</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className={`inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium border ${statusStyles[refund.status] ?? statusStyles.pending}`}>
                                                {refund.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">
                                            {refund.reason}
                                            {refund.failure_reason && (
                                                <span className="block text-xs text-rose-600">
                                                    Gagal: {refund.failure_reason}
                                                </span>
                                            )}
                                            {refund.notes && <span className="block text-xs text-slate-500">Catatan: {refund.notes}</span>}
                                            {refund.proof_of_transfer_url && (
                                                <a
                                                    href={refund.proof_of_transfer_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="block text-xs text-indigo-600 hover:text-indigo-700"
                                                >
                                                    Lihat bukti transfer
                                                </a>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-500">{refund.created_at}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {refund.can_complete ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setCompleting(refund)}
                                                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                                                >
                                                    Tandai Selesai
                                                </button>
                                            ) : (
                                                <span className="text-xs text-slate-400">
                                                    {refund.processed_at ? `Selesai ${refund.processed_at}` : 'Selesai'}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {completing && <CompleteModal refund={completing} onClose={() => setCompleting(null)} />}
        </AppLayout>
    );
}

function CompleteModal({ refund, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        bank_name: '',
        account_number: '',
        account_holder_name: '',
        proof_of_transfer: null,
        notes: '',
    });

    function submit(formEvent) {
        formEvent.preventDefault();
        post(`/admin/refunds/${refund.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div className="w-full max-w-xl rounded-xl bg-white p-6 shadow-xl">
                <h3 className="text-lg font-semibold text-slate-900">Konfirmasi Refund Manual</h3>
                <p className="mt-1 text-sm text-slate-500">
                    Transfer dulu ke rekening customer, lalu catat rekening tujuan di bawah.
                </p>

                <div className="mt-5 space-y-1 rounded-lg border border-slate-200/80 bg-slate-50/60 p-4 text-sm">
                    <p className="font-mono text-slate-800">{refund.order_code}</p>
                    <p className="text-slate-600">{refund.customer_name}</p>
                    <p className="text-slate-600">{refund.event_title ?? '-'}</p>
                    <p className="font-medium tabular-nums text-slate-900">{rupiah(refund.amount)}</p>
                </div>

                <form onSubmit={submit} className="mt-5 space-y-4">
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">
                            Nama Bank <span className="text-rose-500">*</span>
                        </span>
                        <input
                            type="text"
                            value={data.bank_name}
                            onChange={(event) => setData('bank_name', event.target.value)}
                            placeholder="Contoh: BCA"
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                        />
                        {errors.bank_name && <p className="text-sm text-rose-600">{errors.bank_name}</p>}
                    </label>

                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">
                            Nomor Rekening <span className="text-rose-500">*</span>
                        </span>
                        <input
                            type="text"
                            inputMode="numeric"
                            value={data.account_number}
                            onChange={(event) => setData('account_number', event.target.value)}
                            placeholder="Contoh: 1234567890"
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                        />
                        {errors.account_number && <p className="text-sm text-rose-600">{errors.account_number}</p>}
                    </label>

                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">
                            Nama Pemilik Rekening <span className="text-rose-500">*</span>
                        </span>
                        <input
                            type="text"
                            value={data.account_holder_name}
                            onChange={(event) => setData('account_holder_name', event.target.value)}
                            placeholder="Nama sesuai buku rekening"
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                        />
                        {errors.account_holder_name && <p className="text-sm text-rose-600">{errors.account_holder_name}</p>}
                    </label>

                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Bukti Transfer (opsional)</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            onChange={(event) => setData('proof_of_transfer', event.target.files[0] ?? null)}
                            className="w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        {errors.proof_of_transfer && <p className="text-sm text-rose-600">{errors.proof_of_transfer}</p>}
                    </label>

                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Catatan Admin (opsional)</span>
                        <textarea
                            rows={3}
                            value={data.notes}
                            onChange={(event) => setData('notes', event.target.value)}
                            placeholder="Contoh: ditransfer via BCA mobile banking."
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                        />
                        {errors.notes && <p className="text-sm text-rose-600">{errors.notes}</p>}
                    </label>

                    <div className="flex justify-end gap-3 pt-1">
                        <button
                            type="button"
                            onClick={onClose}
                            className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-5 rounded-lg transition-all"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan & Tandai Selesai'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function Flash({ type, children }) {
    const styles = {
        success: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
        error: 'border-rose-200 bg-rose-50/60 text-rose-700',
    };
    return <div className={`mb-6 rounded-lg border px-4 py-3 text-sm ${styles[type]}`}>{children}</div>;
}