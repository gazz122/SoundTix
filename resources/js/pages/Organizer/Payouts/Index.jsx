import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';
import { formatDate, rupiah } from '@/lib/format';

export default function Index({ events }) {
    const { flash } = usePage().props;

    const eligible = events.filter((event) => event.eligible && !event.payout);
    const ineligible = events.filter((event) => !event.eligible);
    const hasRequested = events.filter((event) => event.eligible && event.payout);

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Penarikan Dana</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Tarik hasil penjualan tiket setelah event selesai. Dana ditransfer setelah disetujui admin.
                </p>
            </div>

            {flash.success && <Flash type="success">{flash.success}</Flash>}
            {flash.error && <Flash type="error">{flash.error}</Flash>}

            {events.length === 0 && (
                <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-10 text-center">
                    <p className="text-sm font-medium text-slate-700">Belum ada event yang bisa ditarik dananya.</p>
                    <p className="mt-1 text-sm text-slate-500">Setelah event Anda selesai, tombol penarikan akan muncul di sini.</p>
                </div>
            )}

            {eligible.length > 0 && (
                <div className="mb-10 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wider">Siap Ditarik</h2>
                    {eligible.map((event) => (
                        <WithdrawCard key={event.id} event={event} />
                    ))}
                </div>
            )}

            {hasRequested.length > 0 && (
                <div className="mb-10 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wider">Pengajuan Sebelumnya</h2>
                    {hasRequested.map((event) => (
                        <RequestedCard key={event.id} event={event} />
                    ))}
                </div>
            )}

            {ineligible.length > 0 && (
                <div className="space-y-4">
                    <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wider">Belum Bisa Ditarik</h2>
                    {ineligible.map((event) => (
                        <PendingCard key={event.id} event={event} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function WithdrawCard({ event }) {
    const { data, setData, post, processing, errors } = useForm({
        bank_name: '',
        account_number: '',
        account_holder_name: '',
    });

    function submit(formEvent) {
        formEvent.preventDefault();
        post(`/organizer/payouts/${event.id}/withdraw`, {
            preserveScroll: true,
            onSuccess: () => post.reset(),
        });
    }

    return (
        <div className="bg-white border border-emerald-200/80 rounded-xl overflow-hidden shadow-sm">
            <div className="p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="font-semibold text-slate-900 truncate">{event.title}</h3>
                        <p className="mt-0.5 text-sm text-slate-500">
                            {formatDate(event.event_date)} · {event.status_label}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-semibold text-emerald-700 tabular-nums">{rupiah(event.net_amount)}</p>
                        <p className="text-xs text-slate-500">
                            dari {rupiah(event.gross_amount)}
                            {Number(event.refund_deductions) > 0 && <> − refund {rupiah(event.refund_deductions)}</>}
                            {' '}− fee {rupiah(event.platform_fee)}
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="mt-5 grid gap-3 sm:grid-cols-3">
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Nama Bank</span>
                        <input
                            type="text"
                            value={data.bank_name}
                            onChange={(changeEvent) => setData('bank_name', changeEvent.target.value)}
                            placeholder="cth: BCA"
                            className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                        />
                        {errors.bank_name && <p className="text-sm text-rose-600">{errors.bank_name}</p>}
                    </label>
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">No. Rekening</span>
                        <input
                            type="text"
                            value={data.account_number}
                            onChange={(changeEvent) => setData('account_number', changeEvent.target.value)}
                            placeholder="cth: 1234567890"
                            className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                        />
                        {errors.account_number && <p className="text-sm text-rose-600">{errors.account_number}</p>}
                    </label>
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Nama Pemilik Rekening</span>
                        <input
                            type="text"
                            value={data.account_holder_name}
                            onChange={(changeEvent) => setData('account_holder_name', changeEvent.target.value)}
                            placeholder="cth: PT Soundtix Promoter"
                            className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                        />
                        {errors.account_holder_name && <p className="text-sm text-rose-600">{errors.account_holder_name}</p>}
                    </label>
                    <button
                        type="submit"
                        disabled={processing}
                        className="sm:col-span-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
                    >
                        {processing ? 'Mengirim...' : 'Tarik Dana Sekarang'}
                    </button>
                </form>
            </div>
        </div>
    );
}

function RequestedCard({ event }) {
    const [showProof, setShowProof] = useState(false);
    const { payout } = event;
    const statusColor =
        payout.status === 'failed'
            ? 'border-rose-200 bg-rose-50/60 text-rose-700'
            : payout.status === 'completed'
              ? 'border-emerald-200 bg-emerald-50/60 text-emerald-700'
              : 'border-amber-200 bg-amber-50/60 text-amber-700';

    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="font-semibold text-slate-900 truncate">{event.title}</h3>
                    <p className="mt-0.5 text-sm text-slate-500">{formatDate(event.event_date)}</p>
                </div>
                <div className={`rounded-lg border px-3 py-1.5 text-sm font-medium ${statusColor}`}>
                    {payout.status_label}
                </div>
            </div>
            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <p className="text-xs text-slate-500">Diajukan</p>
                    <p className="font-medium text-slate-800">{payout.created_at}</p>
                </div>
                <div>
                    <p className="text-xs text-slate-500">Rekening</p>
                    <p className="font-medium text-slate-800">
                        {payout.bank_name} · {payout.account_number}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-slate-500">Nominal Bersih</p>
                    <p className="font-medium text-slate-800">{rupiah(payout.net_amount)}</p>
                </div>
            </div>
            {payout.processed_at && (
                <p className="mt-3 text-xs text-slate-500">Diproses {payout.processed_at}</p>
            )}
            {payout.admin_notes && (
                <p className="mt-1 text-sm text-slate-600">Catatan admin: {payout.admin_notes}</p>
            )}
            {payout.status === 'completed' && payout.transfer_proof_url && (
                <div className="mt-4 flex justify-end">
                    <button
                        type="button"
                        onClick={() => setShowProof(true)}
                        className="bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                    >
                        Lihat Bukti Transfer
                    </button>
                </div>
            )}
            {showProof && <ProofModal payout={payout} onClose={() => setShowProof(false)} />}
        </div>
    );
}

function ProofModal({ payout, onClose }) {
    const isPdf = /\.pdf$/i.test(payout.transfer_proof_url);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div className="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900">Bukti Transfer</h3>
                        <p className="mt-0.5 text-sm text-slate-500">Diproses {payout.processed_at}</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-white hover:bg-slate-100 border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition-all"
                    >
                        Tutup
                    </button>
                </div>
                <div className="mt-5 overflow-hidden rounded-xl border border-slate-200/80 bg-slate-50 flex items-center justify-center min-h-64">
                    {isPdf ? (
                        <a
                            href={payout.transfer_proof_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                        >
                            Buka file PDF bukti transfer ↗
                        </a>
                    ) : (
                        <img src={payout.transfer_proof_url} alt="Bukti transfer" className="max-h-[70vh] w-full object-contain" />
                    )}
                </div>
            </div>
        </div>
    );
}

function PendingCard({ event }) {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5 flex flex-wrap items-center justify-between gap-3">
            <div className="min-w-0">
                <h3 className="font-semibold text-slate-900 truncate">{event.title}</h3>
                <p className="mt-0.5 text-sm text-slate-500">
                    {formatDate(event.event_date)} · Pendapatan {rupiah(event.net_amount)}
                </p>
            </div>
            <span className="text-sm text-slate-500">Menunggu event selesai...</span>
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