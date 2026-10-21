import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';
import { rupiah } from '@/lib/format';

const statusStyles = {
    pending: 'border-amber-200 bg-amber-50/60 text-amber-700',
    approved: 'border-blue-200 bg-blue-50/60 text-blue-700',
    processing: 'border-blue-200 bg-blue-50/60 text-blue-700',
    completed: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
    failed: 'border-rose-200 bg-rose-50/60 text-rose-700',
};

export default function Index({ payouts }) {
    const { flash } = usePage().props;
    const [processing, setProcessing] = useState(null);

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Penarikan Dana Organizer</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Proses transfer dana secara manual ke rekening organizer dan unggah bukti transfer.
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
                                <th className="px-4 py-3 text-left">Organizer</th>
                                <th className="px-4 py-3 text-left">Rekening</th>
                                <th className="px-4 py-3 text-right">Refund</th>
                                <th className="px-4 py-3 text-right">Bersih</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Diajukan</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {payouts.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-slate-400">
                                        Belum ada pengajuan penarikan dana.
                                    </td>
                                </tr>
                            ) : (
                                payouts.map((payout) => (
                                    <tr key={payout.id} className="hover:bg-slate-50/50">
                                        <td className="px-4 py-2.5 font-medium text-slate-800">{payout.event ?? '-'}</td>
                                        <td className="px-4 py-2.5 text-slate-700">{payout.organizer ?? '-'}</td>
                                        <td className="px-4 py-2.5 text-slate-600">
                                            {payout.bank_name} · {payout.account_number}
                                            <span className="block text-xs text-slate-400">{payout.account_holder_name}</span>
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">{payout.refund_deductions > 0 ? rupiah(payout.refund_deductions) : '-'}</td>
                                        <td className="px-4 py-2.5 text-right font-medium tabular-nums">{rupiah(payout.net_amount)}</td>
                                        <td className="px-4 py-2.5">
                                            <span className={`inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium border ${statusStyles[payout.status] ?? statusStyles.pending}`}>
                                                {payout.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-500">{payout.created_at}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {payout.status === 'pending' && (
                                                <button
                                                    type="button"
                                                    onClick={() => setProcessing(payout)}
                                                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                                                >
                                                    Proses Transfer
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

            {processing && <ProcessModal payout={processing} onClose={() => setProcessing(null)} />}
        </AppLayout>
    );
}

function ProcessModal({ payout, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        transfer_proof: null,
        admin_notes: '',
    });

    function submit(formEvent) {
        formEvent.preventDefault();
        post(`/admin/payouts/${payout.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div className="max-h-[90vh] w-full max-w-xl overflow-hidden rounded-xl bg-white p-6 shadow-xl">
                <h3 className="text-lg font-semibold text-slate-900">Konfirmasi Payout Manual</h3>
                <p className="mt-1 text-sm text-slate-500">Transfer ke rekening organizer lalu unggah buktinya.</p>

                <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 rounded-lg border border-slate-200/80 bg-slate-50/60 p-4 sm:grid-cols-3">
                    <DetailRow label="Event" value={payout.event ?? '-'} />
                    <DetailRow label="Organizer" value={payout.organizer ?? '-'} />
                    <DetailRow label="Bank" value={payout.bank_name} />
                    <CopyableRow label="No. Rekening" value={payout.account_number} />
                    <DetailRow label="Pemilik Rekening" value={payout.account_holder_name} />
                    <CopyableRow label="Nominal Bersih" value={rupiah(payout.net_amount)} copyValue={String(payout.net_amount)} />
                </div>

                <form onSubmit={submit} className="mt-4 space-y-3">
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">
                            Bukti Transfer (Gambar/PDF) <span className="text-rose-500">*</span>
                        </span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            onChange={(event) => setData('transfer_proof', event.target.files[0] ?? null)}
                            className="w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        {errors.transfer_proof && <p className="text-sm text-rose-600">{errors.transfer_proof}</p>}
                    </label>

                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Catatan Admin (opsional)</span>
                        <textarea
                            rows={2}
                            value={data.admin_notes}
                            onChange={(event) => setData('admin_notes', event.target.value)}
                            placeholder="Contoh: ditransfer ke BCA atas nama PT Soundtix Promoter."
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                        />
                        {errors.admin_notes && <p className="text-sm text-rose-600">{errors.admin_notes}</p>}
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

function DetailRow({ label, value }) {
    return (
        <div className="min-w-0">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-0.5 text-sm font-medium text-slate-800">{value}</p>
        </div>
    );
}

function CopyableRow({ label, value, copyValue }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        await navigator.clipboard.writeText(String(copyValue ?? value));
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    }

    return (
        <div className="min-w-0">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <span className="mt-0.5 flex items-center gap-2">
                <span className="text-sm font-medium tabular-nums text-slate-800">{value}</span>
                <button
                    type="button"
                    onClick={copy}
                    className={`text-xs font-medium px-2 py-1 rounded-md border transition-all ${copied ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-600'}`}
                >
                    {copied ? 'Tersalin ✓' : 'Copy'}
                </button>
            </span>
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